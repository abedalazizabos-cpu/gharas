<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CircleRequest;
use App\Models\Circle;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class CircleController extends Controller
{
    public function index(Request $request)
    {
        $circles = Circle::query()
            ->with('teacher')
            ->withCount(['students' => fn ($q) => $q->active()])
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('code', 'like', "%$t%")))
            ->when($request->query('teacher'), fn ($q, $t) => $q->where('teacher_id', $t))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('name')->paginate(12)->withQueryString();

        return view('admin.circles.index', [
            'circles' => $circles,
            'teachers' => User::role(User::ROLE_TEACHER)->orderBy('name')->get(['id', 'name', 'status']),
        ]);
    }

    public function create()
    {
        return view('admin.circles.form', [
            'circle' => new Circle(['status' => 'active', 'capacity' => 15, 'days' => Circle::STUDY_DAYS, 'is_public' => true]),
            'teachers' => User::role(User::ROLE_TEACHER)->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(CircleRequest $request)
    {
        $circle = Circle::create($request->validated() + ['is_public' => $request->boolean('is_public')]);
        AuditLogger::log('circle.create', "إنشاء حلقة {$circle->name}", $circle, [], $circle->only(['name', 'code', 'teacher_id']));

        return redirect()->route('admin.circles.show', $circle)->with('success', 'تمت إضافة الحلقة بنجاح.');
    }

    public function show(Circle $circle)
    {
        $circle->load(['teacher', 'students' => fn ($q) => $q->orderBy('full_name')->with('parents')]);

        return view('admin.circles.show', [
            'circle' => $circle,
            'history' => $circle->enrollments()->with('student')->whereNotNull('left_at')->latest('left_at')->limit(15)->get(),
            'sessions' => $circle->sessions()->withCount([
                'attendance as present_count' => fn ($q) => $q->whereIn('status', ['present', 'late']),
                'attendance',
            ])->latest('session_date')->limit(10)->get(),
            'recentRecords' => $circle->memorizationRecords()->with(['student', 'surah'])->latest('recorded_on')->limit(10)->get(),
            'teachers' => User::role(User::ROLE_TEACHER)->where(fn ($q) => $q->active()->orWhere('id', $circle->teacher_id))->withCount('circles')->orderBy('name')->get(['id', 'name']),
            'pendingWarnings' => $circle->teacher?->warnings()->pending()->count() ?? 0,
        ]);
    }

    public function edit(Circle $circle)
    {
        return view('admin.circles.form', [
            'circle' => $circle,
            'teachers' => User::role(User::ROLE_TEACHER)->active()->orWhere('id', $circle->teacher_id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(CircleRequest $request, Circle $circle)
    {
        $circle->update($request->validated() + ['is_public' => $request->boolean('is_public')]);
        AuditLogger::logChanges('circle.update', "تعديل حلقة {$circle->name}", $circle);

        return redirect()->route('admin.circles.show', $circle)->with('success', 'تم تحديث بيانات الحلقة بنجاح.');
    }

    public function destroy(Circle $circle)
    {
        if ($circle->students()->exists()) {
            return back()->with('error', 'لا يمكن حذف حلقة بها طلاب. انقل الطلاب أولاً أو أوقف الحلقة.');
        }
        $circle->delete();
        AuditLogger::log('circle.delete', "حذف حلقة {$circle->name}", $circle);

        return redirect()->route('admin.circles.index')->with('success', 'تم حذف الحلقة مع الاحتفاظ بسجلاتها.');
    }
}
