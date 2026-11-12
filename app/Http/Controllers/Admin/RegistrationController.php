<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ManagesGuardians;
use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\RegistrationRequest;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EnrollmentService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Registration requests from the website: accept (creates the student and the guardian account) or decline. */
class RegistrationController extends Controller
{
    use ManagesGuardians;

    public function index(Request $request)
    {
        $status = in_array($request->query('status'), array_keys(RegistrationRequest::STATUSES), true) ? $request->query('status') : 'pending';

        return view('admin.registrations.index', [
            'status' => $status,
            'counts' => RegistrationRequest::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'requests' => RegistrationRequest::where('status', $status)->with(['student.circle', 'handler'])
                ->orderBy($status === 'pending' ? 'created_at' : 'handled_at', $status === 'pending' ? 'asc' : 'desc')->paginate(20)->withQueryString(),
            'circles' => Circle::active()->with('teacher')->withCount(['students' => fn ($q) => $q->active()])->orderBy('name')->get(),
            'parents' => User::where('role', User::ROLE_PARENT)->pluck('name', 'phone'),
        ]);
    }

    public function accept(Request $request, RegistrationRequest $registration, EnrollmentService $enrollment)
    {
        abort_unless($registration->status === 'pending', 422, 'تمت معالجة هذا الطلب من قبل.');
        $data = $request->validate([
            'circle_id' => ['nullable', 'integer', Rule::exists('circles', 'id')->whereNull('deleted_at')],
            'override_capacity' => ['nullable', 'boolean'],
        ], [], ['circle_id' => 'الحلقة']);
        $existing = User::withTrashed()->where('phone', $registration->guardian_phone)->first();
        if ($existing && ($existing->role !== User::ROLE_PARENT || $existing->trashed())) {
            throw ValidationException::withMessages(['circle_id' => "الجوال {$registration->guardian_phone} مسجل لحساب ليس وليّ أمر نشطاً، فأضف الطالب من صفحة «الطلاب» واختر له وليّ أمر آخر."]);
        }
        $override = $request->boolean('override_capacity') && Setting::get('allow_capacity_override') === '1';

        $guardian = null;
        $student = DB::transaction(function () use ($registration, $data, $override, $enrollment, $request, &$guardian) {
            $student = new Student([
                'full_name' => $registration->student_name, 'gender' => $registration->gender, 'birth_date' => $registration->birth_date?->toDateString(),
                'enrollment_date' => now()->toDateString(), 'memorized_juz_at_join' => $registration->memorized_juz,
                'internal_notes' => $registration->notes ? 'ملاحظة وليّ الأمر عند التسجيل: '.$registration->notes : null,
            ]);
            $student->student_number = Student::generateNumber();
            $student->status = 'active';
            $student->save();
            $guardian = $this->attachGuardian($student, ['guardian_phone' => $registration->guardian_phone, 'guardian_name' => $registration->guardian_name, 'guardian_relationship' => $registration->relationship]);
            if (! empty($data['circle_id'])) {
                $enrollment->assign($student, Circle::findOrFail($data['circle_id']), 'تسجيل جديد من الموقع', $override);
            }
            $registration->forceFill(['status' => 'accepted', 'student_id' => $student->id, 'handled_by' => $request->user()->id, 'handled_at' => now()])->save();
            AuditLogger::log('registration.accept', "قبول طلب تسجيل {$student->full_name}", $student, [], $student->only(['student_number', 'full_name']));

            return $student;
        });

        $sms = app(SmsService::class);
        if ($sms->autoEnabled('registration')) {
            $circle = $student->fresh('circle')->circle;
            $login = 'للدخول ومتابعة ابنكم: '.route('login').' برقم جوالكم'.($guardian['password'] ? ' وكلمة المرور '.$guardian['password'] : '');
            $sms->send([$registration->guardian_phone], 'تم قبول تسجيل '.$student->full_name.' في '.Setting::get('center_name').($circle ? ' في «'.$circle->name.'»' : '').'. '.$login, 'registration', $request->user());
        }

        return redirect()->route('admin.students.show', $student)->with('success', 'تم قبول الطلب وإضافة الطالب.')->with('guardian_notice', $this->guardianMessage($guardian));
    }

    public function reject(Request $request, RegistrationRequest $registration)
    {
        abort_unless($registration->status === 'pending', 422);
        $data = $request->validate(['reject_reason' => ['nullable', 'string', 'max:255']]);
        $registration->forceFill(['status' => 'rejected', 'reject_reason' => $data['reject_reason'] ?? null, 'handled_by' => $request->user()->id, 'handled_at' => now()])->save();
        AuditLogger::log('registration.reject', "رفض طلب تسجيل {$registration->student_name}".(! empty($data['reject_reason']) ? ': '.$data['reject_reason'] : ''));

        return back()->with('success', 'تم رفض الطلب.');
    }
}
