<?php

namespace Database\Seeders;

use App\Models\CombinedTestStage;
use App\Models\MemorizationRecord;
use App\Models\QualityCriterion;
use App\Models\QualityTest;
use App\Models\QualityTestScore;
use App\Models\Student;
use App\Models\User;
use App\Services\QualityService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Trial data for the quality committee (لجنة الجودة). Runs inside DemoSeeder, and on its own on an
 * existing trial database: php artisan gharas:demo-quality (never in production).
 *
 * What it shows:
 *  - four examiners: teacher2, teacher6, teacher11 and teacher17 (girls);
 *  - tests follow each student's real memorization (DemoSeeder walks every student juz by juz):
 *    a juz finished during the trial weeks has a passed, waiting, failed or never-sent test;
 *  - combined stages over the ajza known on joining were passed in earlier months, the latest one
 *    is sometimes still waiting or due;
 *  - parent 0550000001: the first child passed everything, the other one waits for the examiner.
 */
class QualityDemoSeeder extends Seeder
{
    private const NOTES = ['حفظ متقن ما شاء الله', 'يحتاج إلى ضبط أحكام المد', 'أخطاء يسيرة في التشابهات', 'تلاوة حسنة وتحتاج سرعة استحضار', 'ممتاز، بارك الله فيه'];

    private $criteria;

    private User $admin;

    public function run(): void
    {
        mt_srand(3030);
        DB::transaction(fn () => $this->seed());
    }

    /** Remove trial quality data so it can be built again (memorization records stay). */
    public static function clear(): void
    {
        QualityTestScore::query()->delete();
        QualityTest::withTrashed()->forceDelete();
        // Records injected by the first version of this seeder.
        MemorizationRecord::where('notes', 'like', '%[تجربة الجودة]%')->forceDelete();
        User::where('is_quality_examiner', true)->update(['is_quality_examiner' => false]);
    }

    private function seed(): void
    {
        $this->criteria = QualityCriterion::active()->get();
        $this->admin = User::admins()->orderBy('id')->firstOrFail();

        $examiners = User::role(User::ROLE_TEACHER)->whereIn('email', ['teacher2@gharas.test', 'teacher6@gharas.test', 'teacher11@gharas.test', 'teacher17@gharas.test'])->get();
        if ($examiners->isEmpty()) {
            $examiners = User::role(User::ROLE_TEACHER)->orderBy('id')->limit(4)->get();
        }
        $examiners->each(fn ($t) => $t->forceFill(['is_quality_examiner' => true])->save());

        $students = Student::active()->whereNotNull('current_circle_id')->with(['circle', 'parents'])->orderBy('id')->get();
        $status = app(QualityService::class)->statusFor($students);
        $firstParent = User::where('phone', '0550000001')->first();

        foreach ($students as $i => $student) {
            $st = $status[$student->id];
            $ex = $examiners[$i % $examiners->count()];
            $showcase = $firstParent && $student->parents->contains('id', $firstParent->id)
                ? ($student->id === $firstParent->children()->orderBy('students.id')->value('students.id') ? 'passed' : 'pending') : null;

            // Ajza finished at the center during these weeks: passed, waiting, failed, or never sent.
            foreach (array_keys($st['missing_individual']) as $juz) {
                $outcome = $showcase ?? ['passed', 'pending', 'failed', null][$student->id % 4];
                if ($outcome) {
                    $this->test($student, $ex, $juz, $juz, null, $outcome, mt_rand(1, 4));
                }
            }

            // Combined stages over ajza known on joining were passed in earlier months (oldest first);
            // the latest one is sometimes still waiting or not sent yet.
            $due = $st['due_combined'];
            foreach ($due as $n => $d) {
                $latest = $n === count($due) - 1;
                $outcome = $showcase === 'passed' ? 'passed' : ($latest ? ['passed', 'pending', null][$student->id % 3] : 'passed');
                if ($outcome) {
                    // Spread between the enrollment date and today, oldest stage first.
                    $since = max(10, (int) $student->enrollment_date->diffInDays(now()));
                    $daysAgo = $outcome === 'passed' ? max(1, $since - intdiv(($n + 1) * $since, count($due) + 1)) : 0;
                    $this->test($student, $ex, $d['stage']->from_juz, $d['stage']->to_juz, $d['stage'], $outcome, $daysAgo);
                }
            }
        }
    }

    private function test(Student $student, User $examiner, int $from, int $to, ?CombinedTestStage $stage, string $status, int $daysAgo = 0): void
    {
        $individual = $stage === null && $from === $to;
        $t = QualityTest::create([
            'student_id' => $student->id, 'kind' => $individual ? 'individual' : 'combined', 'juz' => $individual ? $from : null,
            'stage_id' => $stage?->id, 'from_juz' => $from, 'to_juz' => $to, 'examiner_id' => $examiner->id,
            'assigned_by' => $this->admin->id, 'assigned_on' => now()->subDays($daysAgo + mt_rand(2, 5))->toDateString(), 'status' => 'pending',
        ]);
        if ($status === 'pending') {
            return;
        }

        // Passed results land between 82% and 100%, failed ones between 55% and 75%.
        [$lo, $hi] = $status === 'passed' ? [82, 100] : [55, 75];
        $total = 0;
        foreach ($this->criteria as $c) {
            $score = (int) round($c->max_score * mt_rand($lo, $hi) / 100);
            $t->scores()->create(['quality_criterion_id' => $c->id, 'max_score' => $c->max_score, 'score' => $score]);
            $total += $score;
        }
        $pct = round($total / max(1, $this->criteria->sum('max_score')) * 100, 2);
        $t->update([
            'percentage' => $pct, 'grade' => MemorizationRecord::gradeForScore((int) floor($pct)),
            'status' => $pct >= \App\Services\QualityService::passScore() ? 'passed' : 'failed',
            'tested_on' => now()->subDays($daysAgo)->toDateString(), 'notes' => self::NOTES[mt_rand(0, count(self::NOTES) - 1)],
        ]);
    }
}
