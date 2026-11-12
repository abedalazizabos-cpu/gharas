<?php

namespace App\Http\Controllers;

use App\Models\RegistrationRequest;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Public form: a parent asks to enroll a child; the request waits for the admin. */
class RegistrationController extends Controller
{
    public function create()
    {
        abort_unless(Setting::get('registration_open', '1') === '1', 404);

        return view('public.register');
    }

    public function store(Request $request)
    {
        abort_unless(Setting::get('registration_open', '1') === '1', 404);
        // Hidden field that people never fill in; bots do.
        if (filled($request->input('website'))) {
            return redirect()->route('registration.create')->with('registered', true);
        }
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

        $duplicate = RegistrationRequest::where('status', 'pending')->where('guardian_phone', $data['guardian_phone'])
            ->where('student_name', trim($data['student_name']))->exists();
        if (! $duplicate) {
            $req = RegistrationRequest::create(['student_name' => trim($data['student_name']), 'ip' => $request->ip()] + $data);
            $admins = User::admins()->where('status', 'active')->get()->filter(fn (User $u) => $u->hasPermission('students.manage'));
            foreach ($admins as $admin) {
                $admin->notify(new SystemNotification('طلب تسجيل جديد', "{$req->student_name}، وليّ الأمر {$req->guardian_name} ({$req->guardian_phone}).", route('admin.registrations.index'), 'person-plus'));
            }
        }

        return redirect()->route('registration.create')->with('registered', true);
    }
}
