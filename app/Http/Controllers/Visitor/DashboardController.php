<?php

namespace App\Http\Controllers\Visitor;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\CircleSession;
use App\Services\StatsService;

class DashboardController extends Controller
{
    public function index(StatsService $stats)
    {
        return view('visitor.dashboard', [
            'summary' => $stats->adminSummary(),
            'attendanceTrend' => $stats->attendanceTrend(14),
            'gradeDistribution' => $stats->gradeDistribution(),
            'memorizationTrend' => $stats->memorizationTrend(8),
            'circles' => Circle::active()->with('teacher')->withCount(['students' => fn ($q) => $q->active()])->orderBy('name')->get(),
            'recentSessions' => CircleSession::with('circle.teacher')->withCount([
                'attendance as present_count' => fn ($q) => $q->whereIn('status', ['present', 'late']),
                'attendance',
            ])->latest('session_date')->limit(10)->get(),
        ]);
    }
}
