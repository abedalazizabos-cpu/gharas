<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\MemorizationRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Attendance and memorization of a set of students over a month, shared by the monthly report
 * and the honor board. Amounts are in pages (never ayahs).
 */
class MonthlyStats
{
    /** @return array{0: Carbon, 1: Carbon} first and last day of a "Y-m" month */
    public static function range(string $month): array
    {
        $from = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();

        return [$from, $from->copy()->endOfMonth()->startOfDay()];
    }

    /**
     * @param  Collection<int, int>|array<int, int>  $studentIds
     * @return Collection<int, array{days: int, present: int, late: int, absent: int, excused: int, attendance: ?float, new_pages: float, revision_pages: float, records: int, average: ?float}>
     */
    public function forStudents(Collection|array $studentIds, string $month): Collection
    {
        $ids = collect($studentIds)->values();
        if ($ids->isEmpty()) {
            return collect();
        }
        [$from, $to] = self::range($month);
        $pass = app(ProgressService::class)->passScore();

        $attendance = AttendanceRecord::join('circle_sessions', 'circle_sessions.id', '=', 'attendance_records.circle_session_id')
            ->whereNull('circle_sessions.deleted_at')->whereIn('attendance_records.student_id', $ids)
            ->whereBetween('circle_sessions.session_date', [$from->toDateString(), $to->toDateString()])
            ->get(['attendance_records.student_id', 'attendance_records.status'])->groupBy('student_id');
        $records = MemorizationRecord::whereIn('student_id', $ids)->whereBetween('recorded_on', [$from->toDateString(), $to->toDateString()])
            ->get(['student_id', 'type', 'pages', 'score'])->groupBy('student_id');

        return $ids->mapWithKeys(function ($id) use ($attendance, $records, $pass) {
            $att = $attendance[$id] ?? collect();
            $rec = $records[$id] ?? collect();
            $counts = collect(['present', 'late', 'absent', 'excused'])->mapWithKeys(fn ($s) => [$s => $att->where('status', $s)->count()]);
            $days = $att->count();

            return [$id => [
                'days' => $days,
                ...$counts->all(),
                'attendance' => $days ? round(($counts['present'] + $counts['late']) / $days * 100, 1) : null,
                'new_pages' => round(ProgressService::newPagesIn($rec, $pass), 2),
                'revision_pages' => round((float) $rec->whereIn('type', ['revision', 'consolidation'])->sum('pages'), 2),
                'records' => $rec->count(),
                'average' => $rec->isNotEmpty() ? round((float) $rec->avg('score'), 1) : null,
            ]];
        });
    }
}
