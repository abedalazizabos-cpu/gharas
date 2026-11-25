<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherCircleRequest;
use App\Models\Circle;
use App\Models\RegistrationRequest;
use App\Services\AuditLogger;
use App\Services\ReportService;
use App\Support\Pages;
use App\Support\PdfExport;
use Illuminate\Http\Request;

class CircleController extends Controller
{
    public function index(Request $request)
    {
        return view('teacher.circles.index', [
            'circles' => $request->user()->circles()->withCount(['students' => fn ($q) => $q->active()])->get()->sortBy(fn ($c) => $c->sortKey())->values(),
        ]);
    }

    public function show(Circle $circle)
    {
        $this->authorize('view', $circle);
        $canRequest = $circle->teacher_id === auth()->id() && auth()->user()->hasPermission('teacher.students.request');

        return view('teacher.circles.show', [
            'circle' => $circle,
            'canRequest' => $canRequest,
            // The teacher's requests for this circle: all waiting ones, plus those answered in the last 30 days.
            'requests' => $canRequest ? RegistrationRequest::where('circle_id', $circle->id)->where('requested_by', auth()->id())
                ->where(fn ($q) => $q->where('status', 'pending')->orWhere('handled_at', '>=', now()->subDays(30)))->latest()->get() : collect(),
            'students' => $circle->students()->active()->with('parents')->orderBy('full_name')->get(),
            'sessions' => $circle->sessions()->withCount([
                'attendance as present_count' => fn ($q) => $q->whereIn('status', ['present', 'late']),
                'attendance',
            ])->latest('session_date')->limit(10)->get(),
        ]);
    }

    public function edit(Circle $circle)
    {
        abort_unless($circle->teacher_id === auth()->id(), 403);

        return view('teacher.circles.edit', ['circle' => $circle]);
    }

    /** The teacher renames their circle or changes its time; the change is logged for the administration. */
    public function update(TeacherCircleRequest $request, Circle $circle)
    {
        $circle->update($request->validated());
        AuditLogger::logChanges('circle.update', "تعديل اسم أو موعد حلقة {$circle->name} (من المحفظ)", $circle);

        return redirect()->route('teacher.circles.show', $circle)->with('success', 'تم حفظ اسم الحلقة وموعدها.');
    }

    public function report(Request $request, Circle $circle, ReportService $reports)
    {
        $this->authorize('view', $circle);
        $from = ($request->date('from') ?? now()->startOfMonth())->startOfDay();
        $to = ($request->date('to') ?? now())->endOfDay();
        $rows = $reports->studentRows($circle->students()->active()->orderBy('full_name')->get(), $from, $to, [$circle->id]);

        if (in_array($request->query('export'), ['pdf', 'xlsx'], true)) {
            return PdfExport::download('تقرير-'.$circle->name.'.pdf', "تقرير حلقة {$circle->name}", 'الفترة من '.$from->format('Y/m/d').' إلى '.$to->format('Y/m/d'), [[
                'title' => '', 'headers' => ['رقم الطالب', 'الاسم', 'حاضر', 'متأخر', 'غائب', 'بعذر', 'نسبة الحضور %', 'صفحات حفظ جديد', 'صفحات مراجعة', 'متوسط الدرجة', 'متوسط التقييم %'],
                'rows' => $rows->map(fn ($r) => [$r['student']->student_number, $r['student']->full_name, $r['attendance']['present'], $r['attendance']['late'], $r['attendance']['absent'], $r['attendance']['excused'], $r['attendance_rate'] ?? '—', Pages::num($r['new_pages']), Pages::num($r['revision_pages']), $r['avg_score'] ?? '—', $r['avg_assessment'] ? round($r['avg_assessment'], 1) : '—']),
                'text_cols' => [1],
            ]]);
        }

        return view('shared.students-report', [
            'title' => "تقرير حلقة {$circle->name}",
            'rows' => $rows,
            'totals' => $reports->totals($rows),
            'from' => $from,
            'to' => $to,
            'circle' => $circle,
            'layout' => 'teacher',
        ]);
    }
}
