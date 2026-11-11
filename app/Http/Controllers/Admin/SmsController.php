<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

/** SMS to parents from the center's phone: compose and history (admin), gateway settings (super admin). */
class SmsController extends Controller
{
    public function __construct(private SmsService $sms) {}

    public function index(Request $request)
    {
        return view('admin.sms.index', [
            'configured' => $this->sms->configured(),
            'circles' => Circle::active()->with('teacher')->orderBy('name')->get(),
            'students' => Student::active()->whereHas('parents')->with('circle')->orderBy('full_name')->get(['id', 'full_name', 'current_circle_id']),
            'parentsCount' => $this->parents('all', null)->count(),
            'history' => SmsMessage::with('sender')->latest('id')->paginate(20),
        ]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(['all', 'circle', 'student'])],
            'circle_id' => ['required_if:audience,circle', 'nullable', 'integer', 'exists:circles,id'],
            'student_id' => ['required_if:audience,student', 'nullable', 'integer', 'exists:students,id'],
            'text' => ['required', 'string', 'max:600'],
        ], [], ['text' => 'نص الرسالة', 'circle_id' => 'الحلقة', 'student_id' => 'الطالب']);

        $target = $data['audience'] === 'circle' ? $data['circle_id'] : ($data['audience'] === 'student' ? $data['student_id'] : null);
        $parents = $this->parents($data['audience'], $target);
        if ($parents->isEmpty()) {
            return back()->withInput()->with('error', 'لا يوجد أولياء أمور بأرقام جوال لهذا الاختيار.');
        }
        $result = $this->sms->send($parents->pluck('phone'), $data['text'], 'manual', $request->user());
        AuditLogger::log('sms.send', 'رسالة SMS إلى '.$result['sent'].' رقم: '.mb_substr($data['text'], 0, 80));

        return back()->with($result['failed'] ? 'error' : 'success', $result['failed']
            ? 'لم تُرسل الرسالة إلى '.$result['failed'].' رقم: '.$result['error']
            : 'أُرسلت الرسالة إلى جوال المركز ليرسلها إلى '.$result['sent'].' رقم.');
    }

    public function settings()
    {
        return view('admin.sms.settings', [
            's' => collect(['sms_enabled', 'sms_url', 'sms_username', 'sms_country_code', 'sms_signature'])->mapWithKeys(fn ($k) => [$k => Setting::get($k)])->all()
                + collect(SmsService::AUTO)->mapWithKeys(fn ($v, $k) => ['sms_auto_'.$k => Setting::get('sms_auto_'.$k)])->all(),
            'hasPassword' => (bool) Setting::get('sms_password'),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'sms_url' => ['nullable', 'url', 'max:255'],
            'sms_username' => ['nullable', 'string', 'max:100'],
            'sms_password' => ['nullable', 'string', 'max:200'],
            'sms_country_code' => ['required', 'regex:/^\+?\d{1,4}$/'],
            'sms_signature' => ['nullable', 'string', 'max:40'],
            'test_phone' => ['nullable', 'string', 'max:20'],
        ], [], ['sms_url' => 'عنوان البوابة', 'sms_country_code' => 'رمز الدولة', 'test_phone' => 'رقم التجربة']);

        Setting::put('sms_enabled', $request->boolean('sms_enabled') ? '1' : '0');
        Setting::put('sms_url', ($data['sms_url'] ?? null) ?: null);
        Setting::put('sms_username', ($data['sms_username'] ?? null) ?: null);
        if (! empty($data['sms_password'])) {
            Setting::put('sms_password', Crypt::encryptString($data['sms_password']));
        }
        Setting::put('sms_country_code', '+'.ltrim($data['sms_country_code'], '+'));
        Setting::put('sms_signature', ($data['sms_signature'] ?? null) ?: null);
        foreach (array_keys(SmsService::AUTO) as $k) {
            Setting::put('sms_auto_'.$k, $request->boolean('sms_auto_'.$k) ? '1' : '0');
        }
        AuditLogger::log('settings.update', 'تعديل إعدادات رسائل SMS');

        if (! empty($data['test_phone'])) {
            $r = $this->sms->send([$data['test_phone']], 'رسالة تجربة من موقع '.Setting::get('center_name').'. إن وصلتك فالربط يعمل.', 'test', $request->user());

            return back()->with($r['sent'] ? 'success' : 'error', $r['sent'] ? 'حُفظت الإعدادات وأُرسلت رسالة التجربة إلى الجوال. تأكد من وصولها.' : 'حُفظت الإعدادات، لكن رسالة التجربة لم تُرسل: '.$r['error']);
        }

        return back()->with('success', 'حُفظت إعدادات الرسائل.');
    }

    /** Active parents with a mobile number for the chosen audience. */
    private function parents(string $audience, ?int $id)
    {
        $q = User::role(User::ROLE_PARENT)->active()->whereNotNull('phone')->where('phone', '!=', '');

        return match ($audience) {
            'circle' => $q->whereHas('children', fn ($c) => $c->active()->where('current_circle_id', $id))->get(),
            'student' => $q->whereHas('children', fn ($c) => $c->whereKey($id))->get(),
            default => $q->whereHas('children', fn ($c) => $c->active())->get(),
        };
    }
}
