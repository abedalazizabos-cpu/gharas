<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Circle;
use App\Models\MemorizationRecord;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Dashboard statistics, always computed from the database. */
class StatsService
{
    public function adminSummary(): array
    {
        $today = now()->toDateString();
        $todayAttendance = $this->attendanceCounts(AttendanceRecord::query()
            ->whereHas('session', fn ($q) => $q->whereDate('session_date', $today)));

        return [
            'students_total' => Student::count(),
            'students_active' => Student::active()->count(),
            'teachers' => User::role(User::ROLE_TEACHER)->count(),
            'parents' => User::role(User::ROLE_PARENT)->count(),
            'circles_total' => Circle::count(),
            'circles_active' => Circle::active()->count(),
            'present_today' => $todayAttendance['present'] + $todayAttendance['late'],
            'absent_today' => $todayAttendance['absent'] + $todayAttendance['excused'],
            'attendance_rate_today' => $this->rate($todayAttendance),
        ];
    }

    public function publicSummary(): array
    {
        return [
            'students' => Student::active()->count(),
            'teachers' => User::role(User::ROLE_TEACHER)->active()->count(),
            'circles' => Circle::active()->count(),
        ];
    }

    /** @return array{present:int, late:int, absent:int, excused:int} */
    public function attendanceCounts(Builder $query): array
    {
        $counts = (clone $query)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'present' => (int) ($counts['present'] ?? 0),
            'late' => (int) ($counts['late'] ?? 0),
            'absent' => (int) ($counts['absent'] ?? 0),
            'excused' => (int) ($counts['excused'] ?? 0),
        ];
    }

    public function rate(array $counts): ?float
    {
        $total = array_sum($counts);

        return $total === 0 ? null : round(($counts['present'] + $counts['late']) / $total * 100, 1);
    }

    /** Daily attendance rate for the last N days (optionally limited to circle ids). */
    public function attendanceTrend(int $days = 14, ?array $circleIds = null): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $rows = AttendanceRecord::query()
            ->join('circle_sessions', 'circle_sessions.id', '=', 'attendance_records.circle_session_id')
            ->where('circle_sessions.session_date', '>=', $from->toDateString())
            ->when($circleIds !== null, fn ($q) => $q->whereIn('circle_sessions.circle_id', $circleIds))
            ->selectRaw('circle_sessions.session_date as d, attendance_records.status as s, COUNT(*) as c')
            ->groupBy('d', 's')
            ->get();

        $labels = [];
        $values = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i)->toDateString();
            $day = $rows->filter(fn ($r) => Carbon::parse($r->d)->toDateString() === $date);
            $total = $day->sum('c');
            $labels[] = Carbon::parse($date)->format('m/d');
            $values[] = $total ? round($day->whereIn('s', ['present', 'late'])->sum('c') / $total * 100, 1) : null;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** Count of memorization records by grade (optionally limited to circle ids). */
    public function gradeDistribution(?array $circleIds = null): array
    {
        $counts = MemorizationRecord::query()
            ->when($circleIds !== null, fn ($q) => $q->whereIn('circle_id', $circleIds))
            ->where('recorded_on', '>=', now()->subDays(30)->toDateString())
            ->selectRaw('grade, COUNT(*) as total')->groupBy('grade')->pluck('total', 'grade');

        return collect(MemorizationRecord::GRADES)
            ->map(fn ($label, $key) => ['label' => $label, 'value' => (int) ($counts[$key] ?? 0)])
            ->values()->all();
    }

    /** Pages of passing new memorization per week for the last N weeks. */
    public function memorizationTrend(int $weeks = 8, ?array $circleIds = null): array
    {
        $pass = (int) \App\Models\Setting::get('pass_score', '70');
        $start = now()->startOfWeek(Carbon::SATURDAY)->subWeeks($weeks - 1);
        // Summed per day in SQL, then bucketed into weeks here: a few dozen rows instead of every record.
        $perDay = MemorizationRecord::query()
            ->where('type', 'new')->where('score', '>=', $pass)
            ->where('recorded_on', '>=', $start->toDateString())
            ->when($circleIds !== null, fn ($q) => $q->whereIn('circle_id', $circleIds))
            ->groupBy('recorded_on')->selectRaw('recorded_on as d, SUM(pages) as p')->pluck('p', 'd');

        $labels = [];
        $values = array_fill(0, $weeks, 0.0);
        for ($i = 0; $i < $weeks; $i++) {
            $labels[] = $start->copy()->addWeeks($i)->format('m/d');
        }
        foreach ($perDay as $day => $pages) {
            $i = intdiv((int) $start->diffInDays(Carbon::parse($day)), 7);
            if ($i >= 0 && $i < $weeks) {
                $values[$i] += (float) $pages;
            }
        }

        return ['labels' => $labels, 'values' => $values];
    }

    public function recentActivity(int $limit = 8, ?User $viewer = null)
    {
        return AuditLog::with('user')->when($viewer, fn ($q) => $q->visibleTo($viewer))->latest('created_at')->limit($limit)->get();
    }
}
