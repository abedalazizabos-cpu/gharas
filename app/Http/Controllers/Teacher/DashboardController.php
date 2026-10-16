<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\CircleSession;
use App\Models\MemorizationRecord;
use App\Services\StatsService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, StatsService $stats)
    {
        $teacher = $request->user();
        $circles = $teacher->circles()->active()->withCount(['students' => fn ($q) => $q->active()])->get()->sortBy(fn ($c) => $c->sortKey())->values();
        $circleIds = $circles->pluck('id')->all();
        $today = now();

        $takenToday = CircleSession::whereIn('circle_id', $circleIds)->whereDate('session_date', $today->toDateString())->has('attendance')->pluck('circle_id');
        $weekCounts = $stats->attendanceCounts(AttendanceRecord::query()->whereHas('session', fn ($q) => $q->whereIn('circle_id', $circleIds)
            ->where('session_date', '>=', $today->copy()->subDays(6)->toDateString())));

        return view('teacher.dashboard', [
            'circles' => $circles,
            'todayCircles' => $circles->filter(fn ($c) => $c->meetsOn($today->dayOfWeek)),
            'takenToday' => $takenToday,
            'studentsCount' => $circles->sum('students_count'),
            'weekRate' => $stats->rate($weekCounts),
            'recordsThisWeek' => MemorizationRecord::where('teacher_id', $teacher->id)->where('recorded_on', '>=', $today->copy()->subDays(6)->toDateString())->count(),
            'attendanceTrend' => $stats->attendanceTrend(14, $circleIds),
            'memorizationTrend' => $stats->memorizationTrend(8, $circleIds),
            'recentRecords' => MemorizationRecord::where('teacher_id', $teacher->id)->where('recorded_on', '>=', \App\Services\WeeklyArchiveService::weekStart()->toDateString())->with(['student', 'surah'])->latest('recorded_on')->latest('id')->get(),
            'announcements' => Announcement::current()->visibleTo($teacher)->latest('publish_at')->limit(3)->get(),
            'warnings' => $teacher->warnings()->pending()->with('circle')->latest()->get(),
            'unreadMessages' => $teacher->receivedMessages()->whereNull('read_at')->count(),
        ]);
    }

    public function announcements(Request $request)
    {
        return view('shared.announcements', [
            'announcements' => Announcement::current()->visibleTo($request->user())->latest('publish_at')->paginate(10),
        ]);
    }
}
