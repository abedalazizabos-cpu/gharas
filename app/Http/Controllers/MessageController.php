<?php

namespace App\Http\Controllers;

use App\Http\Requests\MessageRequest;
use App\Models\Message;
use App\Models\Student;
use App\Models\SunnahCourse;
use App\Models\SunnahEnrollment;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Teachers can message parents of students in their circles or in the Sunnah courses they give,
 * and parents can message their children's teachers and course sheikhs. Nobody else is reachable.
 */
class MessageController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $box = $request->query('box') === 'sent' ? 'sent' : 'inbox';
        $query = $box === 'sent' ? $user->sentMessages()->with('recipient') : $user->receivedMessages()->with('sender');

        return view('messages.index', [
            'box' => $box,
            'messages' => $query->with('student')->latest()->paginate(15)->withQueryString(),
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        return view('messages.create', [
            'contacts' => $this->contacts($user),
            'students' => $this->students($user),
            'selectedRecipient' => $request->integer('to') ?: null,
            'selectedStudent' => $request->integer('student') ?: null,
            'subject' => $request->query('subject'),
        ]);
    }

    public function store(MessageRequest $request)
    {
        $user = $request->user();
        $contacts = $this->contacts($user);
        if (! $contacts->contains('id', (int) $request->recipient_id)) {
            throw ValidationException::withMessages(['recipient_id' => 'لا يمكنك مراسلة هذا المستخدم.']);
        }
        if ($request->student_id && ! $this->students($user)->contains('id', (int) $request->student_id)) {
            throw ValidationException::withMessages(['student_id' => 'الطالب المحدد غير مرتبط بك.']);
        }

        $message = new Message($request->validated());
        $message->sender_id = $user->id;
        $message->save();

        $message->recipient->notify(new SystemNotification(
            'رسالة جديدة', "من {$user->name}: {$message->subject}", route('messages.show', $message), 'chat-dots'
        ));

        return redirect()->route('messages.index', ['box' => 'sent'])->with('success', 'تم إرسال الرسالة بنجاح.');
    }

    public function show(Request $request, Message $message)
    {
        $this->authorize('view', $message);

        if ($message->recipient_id === $request->user()->id && ! $message->read_at) {
            $message->forceFill(['read_at' => now()])->save();
        }

        return view('messages.show', ['message' => $message->load('sender', 'recipient', 'student')]);
    }

    /** @return Collection<int, User> */
    private function contacts(User $user): Collection
    {
        if ($user->isTeacher()) {
            // Parents of his circle students and of the students in the Sunnah courses he gives.
            $students = $this->students($user)->pluck('id');

            return User::role(User::ROLE_PARENT)->active()
                ->whereHas('children', fn ($q) => $q->whereIn('students.id', $students))
                ->orderBy('name')->get();
        }

        // The circle teachers of the children and the sheikhs of their Sunnah courses.
        $children = $user->children()->pluck('students.id');

        return User::role(User::ROLE_TEACHER)->active()
            ->where(fn ($q) => $q->whereHas('circles.students', fn ($s) => $s->whereIn('students.id', $children))
                ->orWhereIn('id', SunnahCourse::whereIn('id', SunnahEnrollment::whereIn('student_id', $children)->select('sunnah_course_id'))->whereNotNull('teacher_id')->select('teacher_id')))
            ->orderBy('name')->get();
    }

    /** @return Collection<int, Student> */
    private function students(User $user): Collection
    {
        if ($user->isTeacher()) {
            return Student::query()->where(fn ($q) => $q->whereIn('current_circle_id', $user->circles()->select('id'))
                ->orWhereIn('id', SunnahEnrollment::whereIn('sunnah_course_id', SunnahCourse::where('teacher_id', $user->id)->select('id'))->select('student_id')))
                ->orderBy('full_name')->get();
        }

        return $user->children()->orderBy('full_name')->get();
    }
}
