<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Assessment;
use App\Models\Circle;
use App\Models\MemorizationRecord;
use App\Models\Setting;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Builds per-student rows of attendance / memorization / assessment for a period. */
class ReportService
{
    public function __construct(private StatsService $stats) {}

    /**
     * @param  Collection<int, Student>  $students
     * @return Collection<int, array<string, mixed>>
     */
    public function studentRows(Collection $students, Carbon $from, Carbon $to, ?array $circleIds = null): Collection
    {
        $ids = $students->pluck('id');
        $pass = (int) Setting::get('pass_score', '70');

        $attendance = AttendanceRecord::query()
            ->whereIn('student_id', $ids)
            ->whereHas('session', fn ($q) => $q->whereBetween('session_date', [$from->toDateString(), $to->toDateString()])
                ->when($circleIds !== null, fn ($q) => $q->whereIn('circle_id', $circleIds)))
            ->toBase()->get(['student_id', 'status'])->groupBy('student_id');

        // Plain rows (toBase) rather than models: reports sum thousands of records.
        $memorization = MemorizationRecord::query()
            ->whereIn('student_id', $ids)
            ->whereBetween('recorded_on', [$from->toDateString(), $to->toDateString()])
            ->when($circleIds !== null, fn ($q) => $q->whereIn('circle_id', $circleIds))
            ->toBase()->get(['student_id', 'type', 'score', 'pages'])->groupBy('student_id');

        $assessments = Assessment::query()
            ->whereIn('student_id', $ids)
            ->whereBetween('assessed_on', [$from->toDateString(), $to->toDateString()])
            ->when($circleIds !== null, fn ($q) => $q->whereIn('circle_id', $circleIds))
            ->toBase()->get(['student_id', 'percentage'])->groupBy('student_id');

        return $students->map(function (Student $student) use ($attendance, $memorization, $assessments, $pass) {
            $att = ($attendance[$student->id] ?? collect())->countBy('status')->all();
            $counts = [
                'present' => $att['present'] ?? 0, 'late' => $att['late'] ?? 0,
                'absent' => $att['absent'] ?? 0, 'excused' => $att['excused'] ?? 0,
            ];
            $mem = $memorization[$student->id] ?? collect();

            return [
                'student' => $student,
                'attendance' => $counts,
                'attendance_rate' => $this->stats->rate($counts),
                'new_pages' => ProgressService::newPagesIn($mem, $pass),
                'revision_pages' => (float) $mem->whereIn('type', ['revision', 'consolidation'])->sum('pages'),
                'sessions' => $mem->count(),
                'avg_score' => $mem->count() ? round($mem->avg('score'), 1) : null,
                'avg_assessment' => ($assessments[$student->id] ?? collect())->avg('percentage'),
            ];
        });
    }

    public function totals(Collection $rows): array
    {
        $counts = [
            'present' => $rows->sum('attendance.present'), 'late' => $rows->sum('attendance.late'),
            'absent' => $rows->sum('attendance.absent'), 'excused' => $rows->sum('attendance.excused'),
        ];

        return [
            'attendance' => $counts,
            'attendance_rate' => $this->stats->rate($counts),
            'new_pages' => $rows->sum('new_pages'),
            'revision_pages' => $rows->sum('revision_pages'),
            'sessions' => $rows->sum('sessions'),
            'avg_score' => $rows->whereNotNull('avg_score')->avg('avg_score'),
        ];
    }

    /** Summary row per circle for the center-wide report. */
    public function circleRows(Collection $circles, Carbon $from, Carbon $to): Collection
    {
        return $circles->map(function (Circle $circle) use ($from, $to) {
            $rows = $this->studentRows($circle->students, $from, $to, [$circle->id]);

            return ['circle' => $circle, 'students' => $circle->students->count()] + $this->totals($rows);
        });
    }
}
