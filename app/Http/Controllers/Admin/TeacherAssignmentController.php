<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\TeacherWarning;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Quick admin controls over who teaches a circle, and teacher warnings. */
class TeacherAssignmentController extends Controller
{
    /** Replace a circle's teacher, or leave the circle without one. */
    public function changeCircleTeacher(Request $request, Circle $circle)
    {
        $data = $request->validate([
            'teacher_id' => ['nullable', Rule::exists('users', 'id')->where('role', User::ROLE_TEACHER)->whereNull('deleted_at')],
        ], [], ['teacher_id' => 'المحفظ']);
        $newId = $data['teacher_id'] ?? null;
        $old = $circle->teacher;

        if ((int) $newId === (int) $circle->teacher_id) {
            return back()->with('error', 'هذا المحفظ مسند إلى الحلقة بالفعل.');
        }

        $circle->update(['teacher_id' => $newId]);
        $new = $circle->fresh()->teacher;
        AuditLogger::log('circle.teacher', "تغيير محفظ حلقة {$circle->name}: ".($old?->name ?? 'بدون').' ← '.($new?->name ?? 'بدون'), $circle,
            ['teacher_id' => $old?->id], ['teacher_id' => $new?->id]);
        $this->notifyChange($circle, $old, $new);

        return back()->with('success', $new ? "أصبح {$new->name} محفظ حلقة {$circle->name}." : "أصبحت حلقة {$circle->name} بدون محفظ.");
    }

    /** Move every circle of a teacher to another teacher (or none), optionally deleting the account. */
    public function reassign(Request $request, User $teacher)
    {
        abort_unless($teacher->role === User::ROLE_TEACHER, 404);
        $data = $request->validate([
            'target_id' => ['nullable', Rule::exists('users', 'id')->where('role', User::ROLE_TEACHER)->whereNull('deleted_at')->whereNot('id', $teacher->id)],
            'delete_account' => ['nullable', 'boolean'],
        ], [], ['target_id' => 'المحفظ البديل']);
        $target = isset($data['target_id']) ? User::find($data['target_id']) : null;
        $circles = $teacher->circles()->get();

        DB::transaction(function () use ($circles, $teacher, $target) {
            foreach ($circles as $circle) {
                $circle->update(['teacher_id' => $target?->id]);
                $this->notifyChange($circle, $teacher, $target, notifyOld: false);
            }
        });
        if ($circles->isNotEmpty()) {
            AuditLogger::log('teacher.reassign', "نقل {$circles->count()} حلقة من {$teacher->name} إلى ".($target?->name ?? 'بدون محفظ'), $teacher,
                ['circles' => $circles->pluck('name')->all()], ['teacher_id' => $target?->id]);
            $teacher->notify(new SystemNotification('تغيير في حلقاتك', 'نقلت الإدارة حلقاتك: '.$circles->pluck('name')->implode('، '), route('teacher.dashboard'), 'arrow-left-right'));
        }

        if ($request->boolean('delete_account')) {
            $teacher->delete();
            AuditLogger::log('user.delete', "حذف حساب المحفظ {$teacher->name}", $teacher);

            return redirect()->route('admin.teachers.index')->with('success', "تم نقل حلقات {$teacher->name} وحذف حسابه.");
        }

        return back()->with('success', $circles->isEmpty() ? 'لا توجد حلقات لنقلها.' : "تم نقل {$circles->count()} حلقة إلى ".($target?->name ?? 'بدون محفظ').'.');
    }

    public function warn(Request $request, User $teacher)
    {
        abort_unless($teacher->role === User::ROLE_TEACHER, 404);
        $data = $request->validate([
            'level' => ['required', Rule::in(array_keys(TeacherWarning::LEVELS))],
            'reason' => ['required', 'string', 'max:1000'],
            'circle_id' => ['nullable', Rule::exists('circles', 'id')->where('teacher_id', $teacher->id)],
        ], [], ['level' => 'نوع الإنذار', 'reason' => 'السبب', 'circle_id' => 'الحلقة']);

        $warning = $teacher->warnings()->create($data + ['issued_by' => $request->user()->id]);
        AuditLogger::log('teacher.warn', "{$warning->levelLabel()} للمحفظ {$teacher->name}", $warning, [], $warning->only(['level', 'reason', 'circle_id']));
        $teacher->notify(new SystemNotification($warning->levelLabel().' من الإدارة', $warning->reason, route('teacher.dashboard'), 'exclamation-triangle'));

        return back()->with('success', "تم إرسال {$warning->levelLabel()} إلى {$teacher->name}.");
    }

    public function revokeWarning(TeacherWarning $warning)
    {
        $warning->delete();
        AuditLogger::log('teacher.warn.revoke', "إلغاء {$warning->levelLabel()} للمحفظ {$warning->teacher?->name}", $warning, $warning->only(['level', 'reason']));

        return back()->with('success', 'تم إلغاء الإنذار.');
    }

    private function notifyChange(Circle $circle, ?User $old, ?User $new, bool $notifyOld = true): void
    {
        $new?->notify(new SystemNotification('حلقة جديدة مسندة إليك', "أسندت إليك الإدارة حلقة {$circle->name}", route('teacher.circles.show', $circle), 'bullseye'));
        if ($notifyOld && $old) {
            $old->notify(new SystemNotification('تغيير في حلقاتك', "لم تعد حلقة {$circle->name} مسندة إليك", route('teacher.dashboard'), 'arrow-left-right'));
        }
    }
}
