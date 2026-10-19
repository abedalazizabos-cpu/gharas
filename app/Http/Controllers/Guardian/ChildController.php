<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\ParentFollowService;
use App\Services\ProgressService;
use App\Services\ReportService;
use App\Services\WeeklyArchiveService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ChildController extends Controller
{
    public function show(Request $request, Student $student, ProgressService $progress, ReportService $reports, ParentFollowService $follow)
    {
        // Ownership is checked on the server for every request.
        $this->authorize('view', $student);
        // Opening the child's page counts as seeing every daily record shared so far.
        $follow->markSeen($request->user(), $student);

        $month = $request->query('month') && preg_match('/^\d{4}-\d{2}$/', $request->query('month'))
            ? Carbon::createFromFormat('Y-m', $request->query('month'))->startOfMonth()
            : now()->startOfMonth();
        $summary = $reports->studentRows(collect([$student]), $month->copy(), $month->copy()->endOfMonth())->first();

        return view('parent.child', [
            'student' => $student->load('circle.teacher'),
            'tab' => in_array($request->query('tab'), ['attendance', 'memorization', 'assessments', 'summary'], true) ? $request->query('tab') : 'memorization',
            'progress' => $progress->surahProgress($student),
            'quranPercent' => $progress->quranPercent($student),
            'month' => $month,
            'summary' => $summary,
            'attendance' => $student->attendanceRecords()->with('session.circle')->latest('id')->paginate(15, ['*'], 'att_page')->withQueryString(),
            // Only records the teacher chose to share are visible to parents.
            'records' => $student->memorizationRecords()->where('shared_with_parent', true)->with(['surah', 'teacher'])->latest('recorded_on')->latest('id')->paginate(15, ['*'], 'mem_page')->withQueryString(),
            'assessments' => $student->assessments()->where('shared_with_parent', true)->with(['scores.criterion', 'teacher'])->latest('assessed_on')->paginate(10, ['*'], 'ass_page')->withQueryString(),
        ]);
    }

    /** The child's week (Sunday to Saturday) with a month-by-month list of every past week. */
    public function weeks(Request $request, Student $student, WeeklyArchiveService $weeks, ParentFollowService $follow)
    {
        $this->authorize('view', $student);
        $follow->markSeen($request->user(), $student);
        $start = WeeklyArchiveService::weekStart($request->query('week'));

        $dates = $student->memorizationRecords()->where('shared_with_parent', true)->pluck('recorded_on')
            ->merge($student->attendanceRecords()->with('session:id,session_date')->get()->pluck('session.session_date'))
            ->filter();
        $past = $dates->map(fn ($d) => WeeklyArchiveService::weekStart(Carbon::parse($d))->toDateString())
            ->push(WeeklyArchiveService::weekStart()->toDateString())
            ->unique()->sortDesc()->values()
            ->groupBy(fn ($d) => substr($d, 0, 7));

        return view('parent.weeks', [
            'student' => $student->load('circle.teacher'),
            'start' => $start,
            'w' => $weeks->week($start, null, $student, parentView: true),
            'months' => $past,
        ]);
    }
}
