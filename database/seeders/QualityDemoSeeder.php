<?php

namespace Database\Seeders;

use App\Models\CombinedTestStage;
use App\Models\MemorizationRecord;
use App\Models\QualityCriterion;
use App\Models\QualityTest;
use App\Models\QualityTestScore;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Trial data for the quality committee (لجنة الجودة). Runs inside DemoSeeder, and on its own on an
 * existing trial database: php artisan gharas:demo-quality (never in production).
 *
 * What it shows:
 *  - four examiners: teacher2, teacher6, teacher11 and teacher17 (girls);
 *  - ajza memorized on joining: 0, 1, 3, 5 or 10 depending on the student;
 *  - students who moved to juz 29 without the juz 30 test (alerts), some waiting for the examiner,
 *    some failed, some passed;
 *  - combined tests passed, waiting or due along the ladder 30-28 … 30-21;
 *  - parent 0550000001: one child with passed individual and combined tests, one with an alert.
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

    /** Remove trial quality data so it can be built again. */
    public static function clear(): void
    {
        QualityTestScore::query()->delete();
        QualityTest::withTrashed()->forceDelete();
        MemorizationRecord::where('notes', 'like', '%[تجربة الجودة]%')->forceDelete();
        User::where('is_quality_examiner', true)->update(['is_quality_examiner' => false]);
        Student::query()->update(['memorized_juz_at_join' => 0]);
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

        $stages = CombinedTestStage::active()->get()->keyBy(fn ($s) => $s->from_juz.'-'.$s->to_juz);
        $students = Student::active()->whereNotNull('current_circle_id')->with('circle')->orderBy('id')->get();
        $joinPattern = [0, 0, 0, 1, 3, 5, 10];

        foreach ($students as $i => $student) {
            $joined = $joinPattern[$student->id % 7];
            $student->update(['memorized_juz_at_join' => $joined]);
            $ex = $examiners[$i % $examiners->count()];

            switch ($joined) {
                case 0:
                    if ($student->id % 3 === 0) {
                        // Finished juz 30 and started Al-Mulk (juz 29).
                        $this->newRecord($student, 67, 1, 6, 2);
                        match ($student->id % 4) {
                            0 => $this->test($student, $ex, 30, 30, null, 'passed', 3),
                            1 => $this->test($student, $ex, 30, 30, null, 'pending'),
                            2 => $this->test($student, $ex, 30, 30, null, 'failed', 4),
                            default => null, // never sent: alert
                        };
                    }
                    break;
                case 1:
                    if ($student->id % 2 === 0) {
                        // Knew juz 30, finished juz 29 at the center and is now in juz 28.
                        $this->newRecord($student, 58, 1, 4, 1);
                        $student->id % 4 === 0
                            ? $this->test($student, $ex, 29, 29, null, 'passed', 5)
                            : $this->test($student, $ex, 29, 29, null, 'pending');
                    }
                    break;
                case 3:
                    match ($student->id % 3) {
                        0 => $this->test($student, $ex, 30, 28, $stages['30-28'] ?? null, 'passed', 12),
                        1 => $this->test($student, $ex, 30, 28, $stages['30-28'] ?? null, 'pending'),
                        default => null, // due, not sent yet
                    };
                    break;
                case 5:
                    $this->test($student, $ex, 30, 28, $stages['30-28'] ?? null, 'passed', 50);
                    if ($student->id % 2 === 0) {
                        $this->test($student, $ex, 30, 26, $stages['30-26'] ?? null, 'passed', 9);
                    }
                    break;
                case 10:
                    foreach (['30-28' => 120, '30-26' => 90, '25-23' => 60, '25-21' => 30] as $key => $days) {
                        [$from, $to] = array_map('intval', explode('-', $key));
                        $this->test($student, $ex, $from, $to, $stages[$key] ?? null, 'passed', $days);
                    }
                    $student->id % 2 === 0
                        ? $this->test($student, $ex, 30, 21, $stages['30-21'] ?? null, 'passed', 7)
                        : $this->test($student, $ex, 30, 21, $stages['30-21'] ?? null, 'pending');
                    break;
            }
        }

        $this->firstParent($examiners, $stages);
    }

    /** The parent in the guide (0550000001) sees a full example on the home screen. */
    private function firstParent($examiners, $stages): void
    {
        $parent = User::where('phone', '0550000001')->first();
        $children = $parent?->children()->active()->whereNotNull('current_circle_id')->with('circle')->orderBy('id')->get() ?? collect();
        $ex = $examiners->first();

        if ($first = $children->first()) {
            QualityTest::where('student_id', $first->id)->forceDelete();
            $first->update(['memorized_juz_at_join' => 5]);
            $this->test($first, $ex, 30, 28, $stages['30-28'] ?? null, 'passed', 60);
            $this->test($first, $ex, 30, 26, $stages['30-26'] ?? null, 'passed', 25);
            // Juz 25 learned at the center (now in juz 24) and passed.
            $this->newRecord($first, 40, 1, 7, 2);
            $this->test($first, $examiners[1] ?? $ex, 25, 25, null, 'passed', 1);
        }
        if ($second = $children->get(1)) {
            QualityTest::where('student_id', $second->id)->forceDelete();
            $second->update(['memorized_juz_at_join' => 0]);
            $this->newRecord($second, 67, 1, 6, 1);
            $this->test($second, $ex, 30, 30, null, 'pending');
        }
    }

    private function newRecord(Student $student, int $surah, int $from, int $to, int $daysAgo): void
    {
        MemorizationRecord::create([
            'student_id' => $student->id, 'circle_id' => $student->current_circle_id, 'teacher_id' => $student->circle?->teacher_id,
            'recorded_on' => now()->subDays($daysAgo)->toDateString(), 'type' => 'new', 'surah_id' => $surah,
            'from_ayah' => $from, 'to_ayah' => $to, 'pages' => 0.5, 'score' => mt_rand(80, 98), 'mistakes_count' => mt_rand(0, 2),
            'notes' => 'بداية جزء جديد [تجربة الجودة]', 'shared_with_parent' => true,
        ]);
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
