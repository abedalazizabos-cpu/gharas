<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\Student;
use App\Models\User;
use App\Services\ProgressService;
use App\Services\ReportService;
use App\Support\Pages;
use App\Support\PdfExport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index()
    {
        return view('admin.reports.index', [
            'circles' => Circle::orderBy('name')->get(['id', 'name']),
            'teachers' => User::role(User::ROLE_TEACHER)->orderBy('name')->get(['id', 'name']),
            'students' => Student::orderBy('full_name')->get(['id', 'full_name', 'student_number']),
        ]);
    }

    public function center(Request $request)
    {
        $this->guardExport($request);
        [$from, $to] = $this->period($request);
        $circles = Circle::with(['teacher', 'students' => fn ($q) => $q->active()])->orderBy('name')->get();
        $rows = $this->reports->circleRows($circles, $from, $to);

        if ($request->query('export') === 'pdf') {
            return PdfExport::download('تقرير-المركز.pdf', 'التقرير الإجمالي للمركز', $this->periodLabel($from, $to), [[
                'title' => '', 'headers' => ['الحلقة', 'المحفظ', 'عدد الطلاب', 'نسبة الحضور %', 'صفحات حفظ جديد', 'صفحات مراجعة', 'جلسات التسميع', 'متوسط الدرجة'],
                'rows' => $rows->map(fn ($r) => [$r['circle']->name, $r['circle']->teacher?->name ?? '—', $r['students'], $r['attendance_rate'] ?? '—', Pages::num($r['new_pages']), Pages::num($r['revision_pages']), $r['sessions'], $r['avg_score'] ? round($r['avg_score'], 1) : '—']),
                'text_cols' => [0, 1],
            ]]);
        }

        $all = $rows->pipe(fn ($c) => [
            'students' => $c->sum('students'), 'new_pages' => $c->sum('new_pages'), 'revision_pages' => $c->sum('revision_pages'), 'sessions' => $c->sum('sessions'),
            'attendance_rate' => app(\App\Services\StatsService::class)->rate([
                'present' => $c->sum('attendance.present'), 'late' => $c->sum('attendance.late'),
                'absent' => $c->sum('attendance.absent'), 'excused' => $c->sum('attendance.excused'),
            ]),
        ]);

        return view('admin.reports.center', compact('rows', 'all', 'from', 'to'));
    }

    public function circle(Request $request, Circle $circle)
    {
        return $this->studentsReport($request, $circle->load('teacher')->students()->active()->orderBy('full_name')->get(), [$circle->id], "تقرير حلقة {$circle->name}", 'circle-'.$circle->code, ['circle' => $circle]);
    }

    public function teacher(Request $request, User $teacher)
    {
        abort_unless($teacher->isTeacher(), 404);
        $circleIds = $teacher->circles()->pluck('id')->all();
        $students = Student::active()->whereIn('current_circle_id', $circleIds)->with('circle')->orderBy('full_name')->get();

        return $this->studentsReport($request, $students, $circleIds, "تقرير المحفظ {$teacher->name}", 'teacher-'.$teacher->id, ['teacher' => $teacher]);
    }

    public function student(Request $request, Student $student, ProgressService $progress)
    {
        [$from, $to] = $this->period($request);
        $row = $this->reports->studentRows(collect([$student]), $from, $to)->first();

        return view('admin.reports.student', [
            'student' => $student->load('circle.teacher'),
            'row' => $row,
            'from' => $from,
            'to' => $to,
            'progress' => $progress->surahProgress($student),
            'quranPercent' => $progress->quranPercent($student),
            'records' => $student->memorizationRecords()->with('surah')->whereBetween('recorded_on', [$from->toDateString(), $to->toDateString()])->orderBy('recorded_on')->get(),
            'assessments' => $student->assessments()->with('scores.criterion')->whereBetween('assessed_on', [$from->toDateString(), $to->toDateString()])->orderBy('assessed_on')->get(),
        ]);
    }

    protected function studentsReport(Request $request, $students, array $circleIds, string $title, string $file, array $extra = [])
    {
        $this->guardExport($request);
        [$from, $to] = $this->period($request);
        $rows = $this->presentRows($this->reports->studentRows($students, $from, $to, $circleIds));

        if ($request->query('export') === 'pdf') {
            $masked = $rows->first() && $rows->first()['student']->student_number === null;
            $headers = ['الاسم', 'حاضر', 'متأخر', 'غائب', 'بعذر', 'نسبة الحضور %', 'صفحات حفظ جديد', 'صفحات مراجعة', 'متوسط الدرجة', 'متوسط التقييم %'];

            return PdfExport::download($file.'.pdf', $title, $this->periodLabel($from, $to), [[
                'title' => '', 'headers' => $masked ? $headers : array_merge(['رقم الطالب'], $headers),
                'rows' => $rows->map(fn ($r) => array_merge($masked ? [] : [$r['student']->student_number], [$r['student']->full_name, $r['attendance']['present'], $r['attendance']['late'], $r['attendance']['absent'], $r['attendance']['excused'], $r['attendance_rate'] ?? '—', Pages::num($r['new_pages']), Pages::num($r['revision_pages']), $r['avg_score'] ?? '—', $r['avg_assessment'] ? round($r['avg_assessment'], 1) : '—'])),
                'text_cols' => [$masked ? 0 : 1],
            ]]);
        }

        return view('shared.students-report', [
            'title' => $title,
            'rows' => $rows,
            'totals' => $this->reports->totals($rows),
            'from' => $from,
            'to' => $to,
            'layout' => 'admin',
        ] + $extra);
    }

    /** Hook for subclasses that must hide personal details (see Visitor\ReportController). */
    protected function presentRows($rows)
    {
        return $rows;
    }

    /** Supporters download PDFs only when the super admin allows it. */
    private function guardExport(Request $request): void
    {
        if ($request->query('export') && $request->user()->isVisitor() && ! $request->user()->hasPermission('visitor.download')) {
            abort(403, 'تنزيل الملفات غير مفعّل لحسابك.');
        }
    }

    private function periodLabel(Carbon $from, Carbon $to): string
    {
        return 'الفترة من '.$from->format('Y/m/d').' إلى '.$to->format('Y/m/d');
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function period(Request $request): array
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now();
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from->startOfDay(), $to->endOfDay()];
    }
}
