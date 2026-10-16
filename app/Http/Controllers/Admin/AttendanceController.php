<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\TakesAttendance;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Circle;
use App\Services\StatsService;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use TakesAttendance;

    protected function attendanceRoutePrefix(): string
    {
        return 'admin.attendance';
    }

    /** Daily / weekly / monthly attendance report with filters. */
    public function index(Request $request, StatsService $stats)
    {
        $period = in_array($request->query('period'), ['day', 'week', 'month'], true) ? $request->query('period') : 'day';
        $anchor = $request->date('date') ?? now();
        [$from, $to] = match ($period) {
            'week' => [$anchor->copy()->subDays(6), $anchor->copy()],
            'month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            default => [$anchor->copy(), $anchor->copy()],
        };

        $query = AttendanceRecord::query()
            ->whereHas('session', fn ($q) => $q->whereBetween('session_date', [$from->toDateString(), $to->toDateString()])
                ->when($request->query('circle'), fn ($c, $id) => $c->where('circle_id', $id)))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s));

        $counts = $stats->attendanceCounts(clone $query);

        return view('admin.attendance.index', [
            'records' => $query->with(['student', 'session.circle', 'recorder'])->latest('id')->paginate(25)->withQueryString(),
            'counts' => $counts,
            'rate' => $stats->rate($counts),
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'anchor' => $anchor,
            'circles' => Circle::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
