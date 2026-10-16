<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Circle;
use App\Services\StatsService;

class DashboardController extends Controller
{
    public function index(StatsService $stats)
    {
        return view('admin.dashboard', [
            'summary' => $stats->adminSummary(),
            'attendanceTrend' => $stats->attendanceTrend(14),
            'gradeDistribution' => $stats->gradeDistribution(),
            'memorizationTrend' => $stats->memorizationTrend(8),
            'activity' => $stats->recentActivity(8),
            'circles' => Circle::active()->with('teacher')->withCount(['students' => fn ($q) => $q->active()])->orderBy('name')->get(),
            'announcements' => Announcement::current()->latest('publish_at')->limit(4)->get(),
        ]);
    }
}
