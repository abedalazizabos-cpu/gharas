<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Requests\AttendanceRequest;
use App\Models\Circle;
use App\Models\CircleSession;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Attendance form + save, shared by the admin and teacher areas. */
trait TakesAttendance
{
    abstract protected function attendanceRoutePrefix(): string;

    public function create(Request $request, Circle $circle)
    {
        $this->authorize('manage', $circle);
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();
        if ($date > now()->toDateString()) {
            $date = now()->toDateString();
        }

        $session = CircleSession::where('circle_id', $circle->id)->whereDate('session_date', $date)->first();
        $existing = $session ? $session->attendance()->get()->keyBy('student_id') : collect();

        return view('shared.attendance-form', [
            'circle' => $circle,
            'date' => $date,
            'session' => $session,
            'existing' => $existing,
            'students' => $circle->students()->active()->orderBy('full_name')->get(),
            'meetsToday' => $circle->meetsOn(Carbon::parse($date)->dayOfWeek),
            'routePrefix' => $this->attendanceRoutePrefix(),
        ]);
    }

    public function store(AttendanceRequest $request, Circle $circle, AttendanceService $service)
    {
        $existed = CircleSession::where('circle_id', $circle->id)->whereDate('session_date', $request->date)->exists();
        $service->record($circle, $request->date, $request->input('attendance'), $request->user(), $request->input('session_notes'));

        return redirect()
            ->route($this->attendanceRoutePrefix().'.create', [$circle, 'date' => $request->date])
            ->with('success', $existed ? 'تم تحديث سجل الحضور لهذه الجلسة.' : 'تم حفظ سجل الحضور بنجاح.');
    }
}
