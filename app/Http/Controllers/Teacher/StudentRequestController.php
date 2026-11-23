<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\RegistrationRequest;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A teacher asks to add a new student to his own circle. The request waits in «طلبات التسجيل»
 * and the student joins the circle only when the admin accepts it, within the circle's capacity.
 */
class StudentRequestController extends Controller
{
    public function create(Request $request, Circle $circle)
    {
        $this->ownCircle($request, $circle);

        return view('teacher.student-requests.create', ['circle' => $circle, 'freeSeats' => $circle->freeSeats()]);
    }

    public function store(Request $request, Circle $circle)
    {
        $this->ownCircle($request, $circle);
        $request->merge(['guardian_phone' => User::normalizePhone($request->input('guardian_phone'))]);
        $data = $request->validate([
            'student_name' => ['required', 'string', 'min:6', 'max:150'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'birth_date' => ['required', 'date', 'before:today', 'after:'.now()->subYears(70)->toDateString()],
            'memorized_juz' => ['required', 'integer', 'between:0,30'],
            'guardian_name' => ['required', 'string', 'min:3', 'max:150'],
            'guardian_phone' => ['required', 'regex:/^05\d{8}$/'],
            'relationship' => ['required', Rule::in(array_keys(RegistrationRequest::RELATIONSHIPS))],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'guardian_phone.regex' => 'رقم الجوال يجب أن يبدأ بـ 05 ويتكون من 10 أرقام.',
            'student_name.min' => 'اكتب اسم الطالب ثلاثياً.',
        ], [
            'student_name' => 'اسم الطالب', 'gender' => 'الجنس', 'birth_date' => 'تاريخ الميلاد', 'memorized_juz' => 'الأجزاء المحفوظة',
            'guardian_name' => 'اسم وليّ الأمر', 'guardian_phone' => 'جوال وليّ الأمر', 'relationship' => 'صلة القرابة', 'notes' => 'ملاحظات',
        ]);

        if ($circle->status !== 'active') {
            throw ValidationException::withMessages(['student_name' => 'الحلقة متوقفة، فلا يمكن طلب إضافة طلاب إليها.']);
        }
        if ($circle->freeSeats() < 1) {
            throw ValidationException::withMessages(['student_name' => "الحلقة مكتملة: سعتها {$circle->capacity} طالباً، والمقاعد محجوزة بالطلاب الحاليين والطلبات المنتظرة."]);
        }
        $name = trim($data['student_name']);
        if (RegistrationRequest::pending()->where('guardian_phone', $data['guardian_phone'])->where('student_name', $name)->exists()) {
            throw ValidationException::withMessages(['student_name' => 'يوجد طلب منتظر لهذا الطالب بنفس جوال وليّ الأمر.']);
        }

        $req = new RegistrationRequest(['student_name' => $name, 'ip' => $request->ip()] + $data);
        $req->circle_id = $circle->id;
        $req->requested_by = $request->user()->id;
        $req->save();
        AuditLogger::log('registration.request', "طلب المحفظ إضافة الطالب {$name} إلى «{$circle->name}»");

        $admins = User::admins()->where('status', 'active')->get()->filter(fn (User $u) => $u->hasPermission('students.manage'));
        foreach ($admins as $admin) {
            $admin->notify(new SystemNotification('طلب إضافة طالب من محفظ', "{$request->user()->name} يطلب إضافة {$name} إلى «{$circle->name}».", route('admin.registrations.index'), 'person-plus'));
        }

        return redirect()->route('teacher.circles.show', $circle)->with('success', 'أُرسل الطلب إلى الإدارة. يُضاف الطالب إلى الحلقة بعد موافقة المدير.');
    }

    /** The teacher can withdraw his own request while it is still waiting. */
    public function destroy(Request $request, RegistrationRequest $registration)
    {
        abort_unless($registration->requested_by === $request->user()->id && $registration->status === 'pending', 403);
        $circle = $registration->circle;
        $registration->delete();
        AuditLogger::log('registration.withdraw', "سحب المحفظ طلب إضافة الطالب {$registration->student_name}");

        return redirect()->route('teacher.circles.show', $circle)->with('success', 'تم سحب الطلب.');
    }

    private function ownCircle(Request $request, Circle $circle): void
    {
        abort_unless($circle->teacher_id === $request->user()->id, 403);
    }
}
