<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\CircleDayOff;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\RecordingGapService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Days on which teachers did not record attendance or recitation, and the days circles did not meet. */
class RecordingGapController extends Controller
{
    public function index(Request $request, RecordingGapService $service)
    {
        $to = $request->date('to') ? Carbon::parse($request->date('to'))->min(now()->subDay()) : now()->subDay();
        $from = $request->date('from') ? Carbon::parse($request->date('from')) : $to->copy()->subDays(RecordingGapService::RECENT_DAYS - 1);
        if ($from->gt($to)) {
            $from = $to->copy();
        }
        if ($from->diffInDays($to) > 92) {
            $from = $to->copy()->subDays(92);
        }
        $teacherId = $request->integer('teacher') ?: null;
        $circles = Circle::active()->whereNotNull('teacher_id')->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))->get();
        $gaps = $service->gaps($circles, $from, $to);

        return view('admin.recording-gaps', [
            'from' => $from->startOfDay(),
            'to' => $to->startOfDay(),
            'gaps' => $gaps,
            'byTeacher' => $gaps->groupBy(fn ($g) => $g['teacher']?->id)->sortByDesc(fn ($rows) => $rows->count()),
            'offs' => CircleDayOff::with(['circle.teacher', 'creator'])->whereBetween('off_on', [$from->toDateString(), $to->copy()->addMonth()->toDateString()])
                ->when($teacherId, fn ($q) => $q->where(fn ($w) => $w->whereNull('circle_id')->orWhereIn('circle_id', $circles->pluck('id'))))
                ->orderByDesc('off_on')->get(),
            'teachers' => User::role(User::ROLE_TEACHER)->orderBy('name')->get(['id', 'name']),
            'teacherId' => $teacherId,
        ]);
    }

    /** A day off for one circle (from a gap row) or for the whole center (a holiday). */
    public function storeOff(Request $request)
    {
        $data = $request->validate([
            'circle_id' => ['nullable', 'integer', 'exists:circles,id'],
            'date' => ['required', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date'],
            'reason' => ['required', 'string', 'max:150'],
        ]);
        $from = Carbon::parse($data['date']);
        $to = isset($data['date_to']) ? Carbon::parse($data['date_to'])->min($from->copy()->addDays(60)) : $from->copy();
        $circle = isset($data['circle_id']) ? Circle::find($data['circle_id']) : null;
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            CircleDayOff::updateOrCreate(['circle_id' => $circle?->id, 'off_on' => $day->toDateString()], ['reason' => trim($data['reason']), 'created_by' => $request->user()->id]);
        }
        $period = $from->equalTo($to) ? $from->toDateString() : $from->toDateString().' إلى '.$to->toDateString();
        AuditLogger::log('dayoff.create', ($circle ? "لم تنعقد حلقة «{$circle->name}»" : 'عطلة للمركز كله')." بتاريخ {$period} ({$data['reason']})", $circle);

        return back()->with('success', $circle ? 'سُجّل أن الحلقة لم تنعقد في ذلك اليوم.' : 'سُجّلت العطلة، فلا تُحسب أيامها على المحفظين.');
    }

    public function destroyOff(CircleDayOff $off)
    {
        AuditLogger::log('dayoff.delete', 'إلغاء '.($off->circle ? "عدم انعقاد حلقة «{$off->circle->name}»" : 'عطلة المركز').' بتاريخ '.$off->off_on->toDateString(), $off->circle);
        $off->delete();

        return back()->with('success', 'أُلغي اليوم، فصار يُحسب على المحفظ إن لم يسجّله.');
    }
}
