<?php

namespace App\Services;

use App\Models\CombinedTestStage;
use App\Models\MemorizationRecord;
use App\Models\QualityCriterion;
use App\Models\QualityTest;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Support\Juz;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Quality committee (لجنة الجودة).
 *
 * Students memorize from juz 30 down. A student's current juz is the lowest juz they have
 * reached in their «حفظ جديد» records (or the juz after the ajza they knew when joining).
 * Every juz above it is finished, so:
 *  - an individual test is missing for each finished juz (learned at the center) with no passed test;
 *  - a combined stage is due once all its ajza are finished and it has no passed test.
 */
class QualityService
{
    /**
     * @param  Collection<int, Student>  $students
     * @return Collection<int, array> keyed by student id
     */
    public function statusFor(Collection $students): Collection
    {
        if ($students->isEmpty()) {
            return collect();
        }
        $ids = $students->pluck('id')->all();

        // Lowest point reached in new memorization, encoded as surah*1000 + ayah.
        $positions = MemorizationRecord::query()->whereIn('student_id', $ids)->where('type', 'new')
            ->groupBy('student_id')->select('student_id', DB::raw('MIN(surah_id * 1000 + from_ayah) as pos'))
            ->pluck('pos', 'student_id');
        $tests = QualityTest::whereIn('student_id', $ids)->with(['examiner', 'stage'])->orderBy('assigned_on')->orderBy('id')->get()->groupBy('student_id');
        $stages = CombinedTestStage::active()->get();

        return $students->mapWithKeys(fn (Student $s) => [$s->id => $this->compute($s, $positions[$s->id] ?? null, $tests[$s->id] ?? collect(), $stages)]);
    }

    public function forStudent(Student $student): array
    {
        return $this->statusFor(collect([$student]))->first();
    }

    private function compute(Student $student, ?int $pos, Collection $tests, Collection $stages): array
    {
        $joined = max(0, min(30, (int) $student->memorized_juz_at_join));
        $current = 30 - $joined; // the juz being memorized now (0 = whole mushaf done)
        if ($pos !== null && $current > 0) {
            $current = min($current, Juz::of(intdiv($pos, 1000), $pos % 1000));
        }

        $passed = $tests->where('status', 'passed');
        $pending = $tests->where('status', 'pending');

        // Ajza finished at the center (those known on joining are not flagged).
        $missing = [];
        for ($j = 30 - $joined; $j > $current; $j--) {
            if (! $passed->where('kind', 'individual')->where('juz', $j)->count()) {
                $missing[$j] = $pending->where('kind', 'individual')->where('juz', $j)->first();
            }
        }

        $due = [];
        foreach ($stages as $stage) {
            if ($stage->to_juz > $current && ! $passed->where('kind', 'combined')->where('stage_id', $stage->id)->count()) {
                $due[] = ['stage' => $stage, 'pending' => $pending->where('kind', 'combined')->where('stage_id', $stage->id)->first()];
            }
        }

        return [
            'student' => $student,
            'current_juz' => $current,
            'memorized_count' => 30 - $current,
            'missing_individual' => $missing,
            'due_combined' => $due,
            'tests' => $tests,
            'passed_individual' => $passed->where('kind', 'individual')->sortByDesc('juz')->values(),
            'passed_combined' => $passed->where('kind', 'combined')->sortBy(fn ($t) => $t->stage?->sort_order ?? 999)->values(),
        ];
    }

    /** Students who moved past a juz without passing its test. */
    public function alerts(Collection $students): Collection
    {
        return $this->statusFor($students)->filter(fn ($s) => $s['missing_individual'] !== [])->values();
    }

    /**
     * Send the student to an examiner. A test still waiting for the same juz/stage is handed to
     * the new examiner instead of opening a second one.
     */
    public function assign(Student $student, User $examiner, User $by, ?int $juz = null, ?CombinedTestStage $stage = null): QualityTest
    {
        $kind = $stage ? 'combined' : 'individual';
        $test = QualityTest::where('student_id', $student->id)->pending()->where('kind', $kind)
            ->when($stage, fn ($q) => $q->where('stage_id', $stage->id), fn ($q) => $q->where('juz', $juz))
            ->first() ?? new QualityTest([
                'student_id' => $student->id, 'kind' => $kind, 'juz' => $stage ? null : $juz, 'stage_id' => $stage?->id,
                'from_juz' => $stage ? $stage->from_juz : $juz, 'to_juz' => $stage ? $stage->to_juz : $juz, 'status' => 'pending',
            ]);
        $test->fill(['examiner_id' => $examiner->id, 'assigned_by' => $by->id, 'assigned_on' => now()->toDateString()])->save();
        $test->setRelation('stage', $stage);

        AuditLogger::log('quality.assign', "إسناد {$test->kindLabel()} ({$test->subjectLabel()}) للطالب {$student->full_name} إلى {$examiner->name}", $test);
        $examiner->notify(new SystemNotification('اختبار جودة جديد', "أُسند إليك {$test->kindLabel()} للطالب {$student->full_name}: {$test->subjectLabel()}.", route('teacher.quality.grade', $test), 'patch-check'));

        return $test;
    }

    /** @param array<int, int|string|null> $scores criterion id => score */
    public function grade(QualityTest $test, array $scores, ?string $notes, string $testedOn): QualityTest
    {
        $criteria = QualityCriterion::active()->get();
        $wasPassed = $test->status === 'passed';

        DB::transaction(function () use ($test, $scores, $notes, $testedOn, $criteria) {
            $total = 0;
            $max = 0;
            $test->scores()->delete();
            foreach ($criteria as $c) {
                $score = max(0, min($c->max_score, (int) ($scores[$c->id] ?? 0)));
                $test->scores()->create(['quality_criterion_id' => $c->id, 'max_score' => $c->max_score, 'score' => $score]);
                $total += $score;
                $max += $c->max_score;
            }
            $percentage = $max ? round($total / $max * 100, 2) : 0;
            $test->fill([
                'percentage' => $percentage,
                'grade' => MemorizationRecord::gradeForScore((int) floor($percentage)),
                'status' => $percentage >= self::passScore() ? 'passed' : 'failed',
                'notes' => $notes,
                'tested_on' => $testedOn,
            ])->save();
        });

        $student = $test->student;
        AuditLogger::log('quality.grade', "رصد نتيجة {$test->kindLabel()} ({$test->subjectLabel()}) للطالب {$student->full_name}: {$test->percentage}%", $test);

        if ($test->status === 'passed' && ! $wasPassed) {
            foreach ($student->parents as $parent) {
                $parent->notify(new SystemNotification('اجتاز ابنك اختبار الجودة', "اجتاز {$student->full_name} {$test->kindLabel()} في {$test->subjectLabel()} بتقدير {$test->gradeLabel()}.", route('parent.dashboard'), 'patch-check'));
            }
        }

        return $test;
    }

    public static function passScore(): int
    {
        return (int) (Setting::get('quality_pass_score') ?: 80);
    }
}
