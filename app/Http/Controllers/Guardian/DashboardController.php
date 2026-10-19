<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\ParentFollowService;
use App\Services\ProgressService;
use App\Services\StatsService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, StatsService $stats, ProgressService $progress, ParentFollowService $follow)
    {
        $parent = $request->user();
        $children = $parent->children()->with('circle.teacher')->orderBy('full_name')->get();

        $cards = $children->map(function ($child) use ($stats, $progress) {
            $counts = $stats->attendanceCounts($child->attendanceRecords()->whereHas('session', fn ($q) => $q->where('session_date', '>=', now()->subDays(30)->toDateString()))->getQuery());
            $last = $child->memorizationRecords()->where('shared_with_parent', true)->with('surah')->latest('recorded_on')->latest('id')->first();

            return [
                'student' => $child,
                'attendance_rate' => $stats->rate($counts),
                'absences' => $counts['absent'],
                'memorized_ayahs' => $progress->totalMemorizedAyahs($child),
                'quran_percent' => $progress->quranPercent($child),
                'last_record' => $last,
            ];
        });

        return view('parent.dashboard', [
            'cards' => $cards,
            'follow' => $follow->forParent($parent),
            'announcements' => Announcement::current()->visibleTo($parent)->latest('publish_at')->limit(4)->get(),
            'notifications' => $parent->unreadNotifications()->limit(5)->get(),
        ]);
    }

    public function announcements(Request $request)
    {
        return view('shared.announcements', [
            'announcements' => Announcement::current()->visibleTo($request->user())->latest('publish_at')->paginate(10),
        ]);
    }
}
