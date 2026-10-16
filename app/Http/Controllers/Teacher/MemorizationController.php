<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\RecordsMemorization;
use App\Http\Controllers\Controller;
use App\Http\Requests\MemorizationRecordRequest;
use App\Models\MemorizationRecord;
use App\Models\Student;
use App\Models\Surah;
use App\Notifications\SystemNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class MemorizationController extends Controller
{
    use RecordsMemorization;

    public function index(Request $request)
    {
        $teacher = $request->user();
        $studentIds = $teacher->teacherStudents()->pluck('id');

        return view('teacher.memorization.index', [
            'records' => MemorizationRecord::query()
                ->where(fn ($q) => $q->where('teacher_id', $teacher->id)->orWhereIn('student_id', $studentIds))
                ->with(['student', 'surah', 'circle'])
                ->when($request->query('student'), fn ($q, $s) => $q->where('student_id', $s))
                ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
                ->latest('recorded_on')->latest('id')->paginate(20)->withQueryString(),
            'students' => $teacher->teacherStudents()->orderBy('full_name')->get(['id', 'full_name']),
        ]);
    }

    public function create(Request $request)
    {
        $students = $request->user()->teacherStudents()->active()->with('circle')->orderBy('full_name')->get();
        $selected = $request->integer('student') ?: null;

        return view('shared.memorization-form', [
            'record' => new MemorizationRecord(['recorded_on' => now(), 'type' => 'new', 'shared_with_parent' => true, 'student_id' => $selected]),
            'students' => $students,
            'surahs' => Surah::orderBy('id')->get(),
            'action' => route('teacher.memorization.store'),
            'back' => $selected ? route('teacher.students.show', $selected) : route('teacher.memorization.index'),
        ]);
    }

    public function store(MemorizationRecordRequest $request)
    {
        $student = Student::findOrFail($request->student_id);
        $record = $this->createRecord($request->validated(), $student, $request->user());

        if ($record->shared_with_parent) {
            Notification::send($student->parents, new SystemNotification(
                'تسميع جديد',
                "{$student->full_name}: {$record->typeLabel()} - {$record->rangeLabel()} - {$record->gradeLabel()}",
                route('parent.children.show', $student), 'book'
            ));
        }

        return $request->boolean('add_another')
            ? redirect()->route('teacher.memorization.create', ['student' => $student->id])->with('success', 'تم حفظ سجل التسميع. يمكنك إضافة سجل آخر.')
            : redirect()->route('teacher.students.show', $student)->with('success', 'تم حفظ سجل التسميع بنجاح.');
    }

    public function edit(MemorizationRecord $record)
    {
        $this->authorize('update', $record);

        return view('shared.memorization-form', [
            'record' => $record->load('student'),
            'students' => collect([$record->student]),
            'surahs' => Surah::orderBy('id')->get(),
            'action' => route('teacher.memorization.update', $record),
            'back' => route('teacher.memorization.index'),
        ]);
    }

    public function update(MemorizationRecordRequest $request, MemorizationRecord $record)
    {
        $this->updateRecord($record, $request->validated());

        return redirect()->route('teacher.memorization.index')->with('success', 'تم تعديل سجل التسميع (تم توثيق التعديل).');
    }
}
