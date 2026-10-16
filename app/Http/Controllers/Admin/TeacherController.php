<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesAccounts;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserAccountRequest;
use App\Models\Circle;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherController extends Controller
{
    use ManagesAccounts;

    public function index(Request $request)
    {
        $teachers = User::role(User::ROLE_TEACHER)
            ->with('teacherProfile')
            ->withCount(['circles', 'warnings', 'warnings as pending_warnings_count' => fn ($q) => $q->pending()])
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('email', 'like', "%$t%")->orWhere('phone', 'like', "%$t%")))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('name')->paginate(15)->withQueryString();

        $studentCounts = Circle::query()->whereIn('teacher_id', $teachers->pluck('id'))
            ->withCount(['students' => fn ($q) => $q->active()])->get()
            ->groupBy('teacher_id')->map->sum('students_count');

        return view('admin.teachers.index', compact('teachers', 'studentCounts'));
    }

    public function create()
    {
        return view('admin.teachers.form', ['teacher' => new User(['status' => 'active'])]);
    }

    public function store(UserAccountRequest $request)
    {
        $teacher = DB::transaction(function () use ($request) {
            $teacher = $this->createAccount($request->validated(), User::ROLE_TEACHER);
            $teacher->teacherProfile()->create($request->only(['qualification', 'ijazah', 'hire_date', 'notes']));

            return $teacher;
        });

        return redirect()->route('admin.teachers.show', $teacher)->with('success', 'تم إنشاء حساب المحفظ بنجاح.');
    }

    public function show(User $teacher, ReportService $reports)
    {
        $this->ensureRole($teacher, User::ROLE_TEACHER);
        $circles = $teacher->circles()->with(['students' => fn ($q) => $q->active()->orderBy('full_name')])->get();
        $students = $circles->flatMap->students;
        $rows = $reports->studentRows($students, now()->subDays(30), now(), $circles->pluck('id')->all());

        return view('admin.teachers.show', [
            'teacher' => $teacher->load('teacherProfile'),
            'circles' => $circles,
            'totals' => $reports->totals($rows),
            'studentsCount' => $students->count(),
            'warnings' => $teacher->warnings()->with(['issuer', 'circle'])->latest()->get(),
            'otherTeachers' => User::role(User::ROLE_TEACHER)->active()->whereKeyNot($teacher->id)->withCount('circles')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(User $teacher)
    {
        $this->ensureRole($teacher, User::ROLE_TEACHER);

        return view('admin.teachers.form', ['teacher' => $teacher->load('teacherProfile')]);
    }

    public function update(UserAccountRequest $request, User $teacher)
    {
        $this->ensureRole($teacher, User::ROLE_TEACHER);
        DB::transaction(function () use ($request, $teacher) {
            $this->updateAccount($teacher, $request->validated());
            $teacher->teacherProfile()->updateOrCreate([], $request->only(['qualification', 'ijazah', 'hire_date', 'notes']));
        });

        return redirect()->route('admin.teachers.show', $teacher)->with('success', 'تم تحديث بيانات المحفظ بنجاح.');
    }

    public function status(Request $request, User $teacher)
    {
        $this->ensureRole($teacher, User::ROLE_TEACHER);

        return $this->toggleStatus($request, $teacher);
    }

    public function destroy(User $teacher)
    {
        $this->ensureRole($teacher, User::ROLE_TEACHER);
        if ($teacher->circles()->exists()) {
            return back()->with('error', 'لا يمكن حذف محفظ مسند إليه حلقات. استخدم «نقل حلقات المحفظ» في ملفه، وفيه خيار حذف الحساب بعد النقل.');
        }
        $teacher->delete();
        AuditLogger::log('user.delete', "حذف حساب المحفظ {$teacher->name}", $teacher);

        return redirect()->route('admin.teachers.index')->with('success', 'تم حذف حساب المحفظ مع الاحتفاظ بسجلاته.');
    }
}
