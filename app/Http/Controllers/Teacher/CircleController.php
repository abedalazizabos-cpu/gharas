<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherCircleRequest;
use App\Services\AuditLogger;
use App\Models\Circle;
use App\Services\ReportService;
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

        return view('teacher.circles.show', [
            'circle' => $circle,
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
