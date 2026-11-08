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
            'sunnahCourses' => app(\App\Services\SunnahService::class)->forStudent($student),
            'quality' => app(\App\Services\QualityService::class)->forStudent($student),
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

    /** The child's Sunnah courses: details, result, and the sheikh's instructions and homework. */
    public function courses(Request $request, Student $student, \App\Services\SunnahService $sunnah)
    {
        $this->authorize('view', $student);
        $sunnah->markSeen($request->user(), $student);

        return view('parent.courses', [
            'student' => $student->load('circle'),
            'sunnahCourses' => $sunnah->forStudent($student),
            'follow' => $sunnah->followFor($student),
        ]);
    }

    public function homeworkDone(Request $request, Student $student, \App\Models\SunnahPost $post, \App\Services\SunnahService $sunnah)
    {
        $this->authorize('view', $student);
        abort_unless($post->isHomework() && \App\Models\SunnahEnrollment::where('student_id', $student->id)->where('sunnah_course_id', $post->sunnah_course_id)->exists(), 404);
        $sunnah->setDone($request->user(), $student, $post, $request->boolean('done'));

        return redirect()->to(route('parent.children.courses', $student).'#post-'.$post->id)
            ->with('success', $request->boolean('done') ? 'جزاك الله خيراً، سُجّل أن ابنك أنجز الواجب.' : 'أُلغي تأكيد إنجاز الواجب.');
    }

    /** The child's week (Saturday to Thursday) with a month-by-month list of every past week. */
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
