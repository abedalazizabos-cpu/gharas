<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** A teacher sends SMS to the parents of his own circles' students (once the center has connected SMS). */
class SmsController extends Controller
{
    public function __construct(private SmsService $sms) {}

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($this->sms->configured(), 404);
        $circles = $user->circles()->active()->orderBy('name')->get();

        return view('teacher.sms.index', [
            'circles' => $circles,
            'students' => $user->teacherStudents()->active()->whereHas('parents')->with('circle')->orderBy('full_name')->get(['id', 'full_name', 'current_circle_id']),
            'counts' => $circles->mapWithKeys(fn ($c) => [$c->id => $this->parents($user, 'circle', $c->id)->count()]),
            'history' => SmsMessage::where('sent_by', $user->id)->latest('id')->paginate(15),
        ]);
    }

    public function send(Request $request)
    {
        $user = $request->user();
        abort_unless($this->sms->configured(), 404);
        $data = $request->validate([
            'audience' => ['required', Rule::in(['circle', 'student'])],
            'circle_id' => ['required_if:audience,circle', 'nullable', 'integer', Rule::exists('circles', 'id')->where('teacher_id', $user->id)],
            'student_id' => ['required_if:audience,student', 'nullable', 'integer', Rule::in($user->teacherStudents()->pluck('id')->all())],
            'text' => ['required', 'string', 'max:600'],
        ], [], ['text' => 'نص الرسالة', 'circle_id' => 'الحلقة', 'student_id' => 'الطالب']);

        $parents = $this->parents($user, $data['audience'], (int) ($data['audience'] === 'circle' ? $data['circle_id'] : $data['student_id']));
        if ($parents->isEmpty()) {
            return back()->withInput()->with('error', 'لا يوجد أولياء أمور بأرقام جوال لهذا الاختيار.');
        }
        // The parent sees who wrote it: «الشيخ فلان: …»
        $result = $this->sms->send($parents->pluck('phone'), $user->name.': '.$data['text'], 'teacher', $user);
        AuditLogger::log('sms.send', 'رسالة SMS من المحفظ إلى '.$result['sent'].' رقم: '.mb_substr($data['text'], 0, 80));

        return back()->with($result['failed'] ? 'error' : 'success', $result['failed']
            ? 'لم تُرسل الرسالة إلى '.$result['failed'].' رقم: '.$result['error']
            : 'أُرسلت الرسالة إلى '.$result['sent'].' من أولياء الأمور.');
    }

    /** Active parents with a mobile number, only of this teacher's active students. */
    private function parents(User $teacher, string $audience, int $id)
    {
        $mine = $teacher->circles()->select('id');

        return User::role(User::ROLE_PARENT)->active()->whereNotNull('phone')->where('phone', '!=', '')
            ->whereHas('children', fn ($c) => $c->active()->whereIn('current_circle_id', $mine)
                ->when($audience === 'circle', fn ($q) => $q->where('current_circle_id', $id))
                ->when($audience === 'student', fn ($q) => $q->whereKey($id)))
            ->get();
    }
}
