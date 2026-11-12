<?php

namespace App\Services;

use App\Models\Circle;
use App\Models\HonorEntry;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * لوحة الشرف: each month one student is chosen from every active circle, by the month's recitation
 * scores, attendance and new pages (compared with the rest of the circle). The board of a month is
 * shown on the login screen during the month after it, and the admin may replace any choice.
 */
class HonorBoardService
{
    /** Weights of the points (out of 100). */
    public const WEIGHTS = ['average' => 40, 'attendance' => 30, 'pages' => 30];

    public function __construct(private MonthlyStats $stats) {}

    /** The month whose board is shown now: last month. */
    public static function currentMonth(): string
    {
        return now()->startOfMonth()->subMonth()->format('Y-m');
    }

    /** The board shown now, chosen on first use in the month (the scheduler also does this on the 1st). */
    public function current(): Collection
    {
        $month = self::currentMonth();
        $this->ensure($month);

        return $this->board($month);
    }

    public function board(string $month): Collection
    {
        return HonorEntry::where('month', $month)->whereHas('student', fn ($q) => $q->whereNull('deleted_at'))
            ->with(['student', 'circle.teacher', 'picker'])->get()
            ->sortBy(fn (HonorEntry $e) => $e->circle?->name)->values();
    }

    /** Chooses the month's students once; later calls do nothing. Returns how many were chosen. */
    public function ensure(string $month): int
    {
        if (Setting::get('honor_chosen_'.$month) === '1') {
            return 0;
        }
        Setting::put('honor_chosen_'.$month, '1');

        return $this->choose($month, notify: true);
    }

    /**
     * (Re)chooses the month's students automatically. Circles where the admin picked a student by hand
     * keep that student. Parents of the chosen students are told.
     */
    public function choose(string $month, bool $notify = false): int
    {
        $chosen = collect();
        $circles = Circle::active()->with(['students' => fn ($q) => $q->active()])->get();
        $manual = HonorEntry::where('month', $month)->whereNotNull('picked_by')->pluck('circle_id')->flip();

        DB::transaction(function () use ($circles, $manual, $month, &$chosen) {
            foreach ($circles as $circle) {
                if (isset($manual[$circle->id])) {
                    continue;
                }
                $best = $this->ranking($circle, $month)->first();
                if (! $best) {
                    HonorEntry::where('month', $month)->where('circle_id', $circle->id)->delete();

                    continue;
                }
                $chosen->push(HonorEntry::updateOrCreate(['month' => $month, 'circle_id' => $circle->id], [
                    'student_id' => $best['student']->id, 'points' => $best['points'], 'details' => $best['stats'], 'picked_by' => null,
                ]));
            }
        });

        if ($notify) {
            $chosen->each(fn (HonorEntry $e) => $this->congratulate($e));
        }

        return $chosen->count();
    }

    /**
     * The circle's students ranked for a month: only students with recitation that month are ranked.
     *
     * @return Collection<int, array{student: Student, points: float, stats: array}>
     */
    public function ranking(Circle $circle, string $month): Collection
    {
        $students = $circle->relationLoaded('students') ? $circle->students : $circle->students()->active()->get();
        $stats = $this->stats->forStudents($students->pluck('id'), $month);
        $maxPages = max(0.01, (float) $stats->max('new_pages'));

        return $students->map(function (Student $s) use ($stats, $maxPages) {
            $st = $stats[$s->id] ?? null;
            if (! $st || ! $st['records']) {
                return null;
            }
            $points = self::WEIGHTS['average'] * ($st['average'] ?? 0) / 100
                + self::WEIGHTS['attendance'] * ($st['attendance'] ?? 0) / 100
                + self::WEIGHTS['pages'] * min(1, $st['new_pages'] / $maxPages);

            return ['student' => $s, 'points' => round($points, 2), 'stats' => $st];
        })->filter()->sortBy([['points', 'desc'], [fn ($r) => $r['stats']['new_pages'], 'desc'], [fn ($r) => $r['student']->full_name, 'asc']])->values();
    }

    /** The admin puts another student of the same circle on the board instead. */
    public function replace(HonorEntry $entry, Student $student, User $by): HonorEntry
    {
        abort_unless($student->current_circle_id === $entry->circle_id && $student->status === 'active', 422, 'الطالب ليس في هذه الحلقة.');
        $old = $entry->student?->full_name;
        $stats = $this->stats->forStudents([$student->id], $entry->month)[$student->id];
        $entry->update(['student_id' => $student->id, 'picked_by' => $by->id, 'details' => $stats]);
        AuditLogger::log('honor.replace', "لوحة الشرف ({$entry->circle?->name}، ".HonorEntry::monthLabel($entry->month)."): {$student->full_name} بدلاً من {$old}", $entry);
        $this->congratulate($entry->fresh(['student', 'circle']));

        return $entry;
    }

    private function congratulate(HonorEntry $entry): void
    {
        $student = $entry->student;
        if (! $student) {
            return;
        }
        $month = HonorEntry::monthLabel($entry->month);
        foreach ($student->parents as $parent) {
            $parent->notify(new SystemNotification('لوحة الشرف', "مبارك! {$student->full_name} في لوحة شرف المركز عن شهر {$month}، الأول في «{$entry->circle?->name}».", route('parent.children.show', $student), 'trophy'));
        }
    }
}
