<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\CircleDayOff;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The teacher says a circle did not meet on a day, with the reason; the admin sees it. */
class DayOffController extends Controller
{
    public function store(Request $request, Circle $circle)
    {
        abort_unless($circle->teacher_id === $request->user()->id, 403);
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'max:150', Rule::in(CircleDayOff::REASONS)],
            'note' => ['nullable', 'string', 'max:120'],
        ]);
        $reason = $data['reason'].(! empty($data['note']) ? ': '.trim($data['note']) : '');
        CircleDayOff::updateOrCreate(['circle_id' => $circle->id, 'off_on' => $data['date']], ['reason' => $reason, 'created_by' => $request->user()->id]);
        AuditLogger::log('dayoff.create', "لم تنعقد حلقة «{$circle->name}» بتاريخ {$data['date']} ({$reason})", $circle);

        // Announced on the day itself: the parents are told there is no class today.
        if ($data['date'] === now()->toDateString()) {
            foreach ($circle->students()->active()->with('parents')->get() as $student) {
                foreach ($student->parents as $parent) {
                    $parent->notify(new \App\Notifications\SystemNotification('لا تحفيظ اليوم', "لا تنعقد {$circle->name} اليوم ({$data['reason']}). نعتذر عن ذلك، ونلقاكم في الموعد القادم بإذن الله.", route('parent.children.show', $student), 'calendar-x'));
                }
            }

            return back()->with('success', 'سُجّل أنه لا تحفيظ اليوم، وأُشعر أولياء الأمور، وستراه الإدارة مع السبب.');
        }

        return back()->with('success', 'سُجّل أن الحلقة لم تنعقد في ذلك اليوم، وستراه الإدارة مع السبب.');
    }
}
