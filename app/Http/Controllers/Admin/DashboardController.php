<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Circle;
use App\Services\StatsService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, StatsService $stats)
    {
        return view('admin.dashboard', [
            'summary' => $stats->adminSummary(),
            'attendanceTrend' => $stats->attendanceTrend(14),
            'gradeDistribution' => $stats->gradeDistribution(),
            'memorizationTrend' => $stats->memorizationTrend(8),
            'activity' => $stats->recentActivity(8, $request->user()),
            'circles' => Circle::active()->with('teacher')->withCount(['students' => fn ($q) => $q->active()])->orderBy('name')->get(),
            'announcements' => Announcement::current()->latest('publish_at')->limit(4)->get(),
        ]);
    }
}
