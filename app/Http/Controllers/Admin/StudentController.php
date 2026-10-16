<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ManagesGuardians;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudentRequest;
use App\Http\Requests\TransferRequest;
use App\Models\Circle;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EnrollmentService;
use App\Services\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    use ManagesGuardians;

    public function __construct(private EnrollmentService $enrollment) {}

    public function index(Request $request)
    {
        $students = Student::query()
            ->with(['circle.teacher', 'parents'])
            ->search($request->query('q'))
            ->when($request->query('circle'), fn ($q, $c) => $c === 'none' ? $q->whereNull('current_circle_id') : $q->where('current_circle_id', $c))
            ->when($request->query('teacher'), fn ($q, $t) => $q->whereHas('circle', fn ($c) => $c->where('teacher_id', $t)))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy($this->sortColumn($request), $request->query('dir') === 'desc' ? 'desc' : 'asc')
            ->paginate(15)->withQueryString();

        return view('admin.students.index', [
            'students' => $students,
            'circles' => Circle::orderBy('name')->get(['id', 'name']),
            'teachers' => User::role(User::ROLE_TEACHER)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return view('admin.students.form', $this->formData(new Student([
            'enrollment_date' => now()->toDateString(),
            'student_number' => Student::generateNumber(),
        ])));
    }

    public function store(StudentRequest $request)
    {
        $data = $request->validated();
        $override = $request->boolean('override_capacity') && Setting::get('allow_capacity_override') === '1';

        $guardian = null;
        $student = DB::transaction(function () use ($data, $override, &$guardian) {
            $student = new Student($data);
            $student->student_number = ($data['student_number'] ?? null) ?: Student::generateNumber();
            $student->status = 'active';
            $student->save();

            $student->parents()->sync($data['parent_ids'] ?? []);
            $guardian = $this->attachGuardian($student, $data);
            AuditLogger::log('student.create', "إضافة الطالب {$student->full_name}", $student, [], $student->only(['student_number', 'full_name']));

            if (! empty($data['circle_id'])) {
                $this->enrollment->assign($student, Circle::findOrFail($data['circle_id']), 'تسجيل جديد', $override);
            }

            return $student;
        });

        return redirect()->route('admin.students.show', $student)->with('success', 'تمت إضافة الطالب بنجاح.')->with('guardian_notice', $this->guardianMessage($guardian));
    }

    public function show(Student $student, ProgressService $progress)
    {
        $student->load(['circle.teacher', 'parents', 'enrollments.circle']);

        return view('admin.students.show', [
            'student' => $student,
            'progress' => $progress->surahProgress($student),
            'quranPercent' => $progress->quranPercent($student),
            'attendance' => $student->attendanceRecords()->with('session.circle')->latest('id')->paginate(10, ['*'], 'att_page'),
            'records' => $student->memorizationRecords()->with(['surah', 'teacher'])->latest('recorded_on')->latest('id')->paginate(10, ['*'], 'mem_page'),
            'assessments' => $student->assessments()->with(['scores.criterion', 'teacher'])->latest('assessed_on')->paginate(5, ['*'], 'ass_page'),
            'circles' => Circle::active()->withCount(['students' => fn ($q) => $q->active()])->orderBy('name')->get(),
        ]);
    }

    public function edit(Student $student)
    {
        return view('admin.students.form', $this->formData($student->load('parents')));
    }

    public function update(StudentRequest $request, Student $student)
    {
        $data = $request->validated();

        $guardian = null;
        DB::transaction(function () use ($student, $data, &$guardian) {
            $student->fill($data);
            $student->save();
            AuditLogger::logChanges('student.update', "تعديل بيانات الطالب {$student->full_name}", $student);

            $changes = $student->parents()->sync($data['parent_ids'] ?? []);
            if ($changes['attached'] || $changes['detached']) {
                AuditLogger::log('student.parents', "تعديل أولياء أمور الطالب {$student->full_name}", $student, ['detached' => $changes['detached']], ['attached' => $changes['attached']]);
            }
            $guardian = $this->attachGuardian($student, $data);
        });

        return redirect()->route('admin.students.show', $student)->with('success', 'تم تحديث بيانات الطالب بنجاح.')->with('guardian_notice', $this->guardianMessage($guardian));
    }

    public function status(Request $request, Student $student)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Student::STATUSES))]]);
        $old = $student->status;

        DB::transaction(function () use ($student, $data) {
            $student->status = $data['status'];
            $student->withdrawn_at = $data['status'] === 'withdrawn' ? now() : null;
            $student->save();

            // A withdrawn student leaves the circle; history is kept in circle_enrollments.
            if ($data['status'] === 'withdrawn' && $student->current_circle_id) {
                $this->enrollment->assign($student, null, 'انسحاب الطالب');
            }
        });

        AuditLogger::log('student.status', "تغيير حالة الطالب {$student->full_name}", $student, ['status' => $old], ['status' => $student->status]);

        return back()->with('success', 'تم تحديث حالة الطالب إلى «'.$student->statusLabel().'».');
    }

    public function transfer(TransferRequest $request, Student $student)
    {
        $circle = $request->circle_id ? Circle::findOrFail($request->circle_id) : null;
        $override = $request->boolean('override_capacity') && Setting::get('allow_capacity_override') === '1';

        $this->enrollment->assign($student, $circle, $request->reason, $override);

        return back()->with('success', $circle ? "تم نقل الطالب إلى حلقة «{$circle->name}» مع الاحتفاظ بسجلاته السابقة." : 'تم إخراج الطالب من الحلقة.');
    }

    public function destroy(Student $student)
    {
        // Soft delete keeps attendance, memorization and assessment history.
        $student->delete();
        AuditLogger::log('student.delete', "حذف (أرشفة) الطالب {$student->full_name}", $student);

        return redirect()->route('admin.students.index')->with('success', 'تم حذف الطالب (أرشفة) مع الاحتفاظ بسجلاته التعليمية.');
    }

    private function formData(Student $student): array
    {
        return [
            'student' => $student,
            'circles' => Circle::active()->withCount(['students' => fn ($q) => $q->active()])->orderBy('name')->get(),
        ];
    }

    private function sortColumn(Request $request): string
    {
        return in_array($request->query('sort'), ['full_name', 'student_number', 'enrollment_date', 'status'], true)
            ? $request->query('sort') : 'full_name';
    }
}
