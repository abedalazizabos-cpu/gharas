<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\ManagesGuardians;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudentRequest;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\ProgressService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    use ManagesGuardians;

    public function show(Student $student, ProgressService $progress)
    {
        $this->authorize('view', $student);

        return view('teacher.students.show', [
            'student' => $student->load(['circle', 'parents']),
            'progress' => $progress->surahProgress($student),
            'quranPercent' => $progress->quranPercent($student),
            'attendance' => $student->attendanceRecords()->with('session.circle')->latest('id')->paginate(10, ['*'], 'att_page'),
            'records' => $student->memorizationRecords()->with(['surah', 'teacher'])->latest('recorded_on')->latest('id')->paginate(10, ['*'], 'mem_page'),
            'assessments' => $student->assessments()->with(['scores.criterion', 'teacher'])->latest('assessed_on')->paginate(5, ['*'], 'ass_page'),
        ]);
    }

    public function edit(Student $student)
    {
        $this->authorize('manage', $student);

        return view('admin.students.form', ['student' => $student->load(['parents', 'circle']), 'circles' => collect(), 'prefix' => 'teacher']);
    }

    /** The teacher edits the student's data and guardians; number, circle and admin notes stay with the administration. */
    public function update(StudentRequest $request, Student $student)
    {
        $data = Arr::except($request->validated(), ['student_number', 'internal_notes', 'circle_id', 'override_capacity']);

        $guardian = null;
        DB::transaction(function () use ($student, $data, &$guardian) {
            $student->fill($data);
            $student->save();
            AuditLogger::logChanges('student.update', "تعديل بيانات الطالب {$student->full_name} (من المحفظ)", $student);

            $changes = $student->parents()->sync($data['parent_ids'] ?? []);
            if ($changes['attached'] || $changes['detached']) {
                AuditLogger::log('student.parents', "تعديل أولياء أمور الطالب {$student->full_name} (من المحفظ)", $student, ['detached' => $changes['detached']], ['attached' => $changes['attached']]);
            }
            $guardian = $this->attachGuardian($student, $data);
        });

        return redirect()->route('teacher.students.show', $student)->with('success', 'تم تحديث بيانات الطالب بنجاح.')->with('guardian_notice', $this->guardianMessage($guardian));
    }
}
