<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesAccounts;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserAccountRequest;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParentController extends Controller
{
    use ManagesAccounts;

    public function index(Request $request)
    {
        $parents = User::role(User::ROLE_PARENT)
            ->with(['children:id,full_name', 'parentProfile'])
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('email', 'like', "%$t%")->orWhere('phone', 'like', "%$t%")))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('linked') === 'no', fn ($q) => $q->doesntHave('children'))
            ->when($request->query('linked') === 'yes', fn ($q) => $q->has('children'))
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('admin.parents.index', compact('parents'));
    }

    public function create()
    {
        return view('admin.parents.form', $this->formData(new User(['status' => 'active'])));
    }

    public function store(UserAccountRequest $request)
    {
        $parent = DB::transaction(function () use ($request) {
            $parent = $this->createAccount($request->validated(), User::ROLE_PARENT);
            $parent->parentProfile()->create($request->only(['national_id', 'alt_phone', 'address']));
            $this->syncChildren($parent, $request);

            return $parent;
        });

        return redirect()->route('admin.parents.show', $parent)->with('success', 'تم إنشاء حساب ولي الأمر بنجاح.');
    }

    public function show(User $parent)
    {
        $this->ensureRole($parent, User::ROLE_PARENT);

        return view('admin.parents.show', ['parent' => $parent->load(['parentProfile', 'children.circle.teacher'])]);
    }

    public function edit(User $parent)
    {
        $this->ensureRole($parent, User::ROLE_PARENT);

        return view('admin.parents.form', $this->formData($parent->load(['parentProfile', 'children'])));
    }

    public function update(UserAccountRequest $request, User $parent)
    {
        $this->ensureRole($parent, User::ROLE_PARENT);
        DB::transaction(function () use ($request, $parent) {
            $this->updateAccount($parent, $request->validated());
            $parent->parentProfile()->updateOrCreate([], $request->only(['national_id', 'alt_phone', 'address']));
            $this->syncChildren($parent, $request);
        });

        return redirect()->route('admin.parents.show', $parent)->with('success', 'تم تحديث بيانات ولي الأمر بنجاح.');
    }

    public function status(Request $request, User $parent)
    {
        $this->ensureRole($parent, User::ROLE_PARENT);

        return $this->toggleStatus($request, $parent);
    }

    public function destroy(User $parent)
    {
        $this->ensureRole($parent, User::ROLE_PARENT);
        $parent->delete();
        AuditLogger::log('user.delete', "حذف حساب ولي الأمر {$parent->name}", $parent);

        return redirect()->route('admin.parents.index')->with('success', 'تم حذف حساب ولي الأمر.');
    }

    private function syncChildren(User $parent, Request $request): void
    {
        $relationship = $request->input('relationship', 'father');
        $ids = collect($request->input('student_ids', []))->mapWithKeys(fn ($id) => [(int) $id => ['relationship' => $relationship]]);
        $changes = $parent->children()->sync($ids->all());

        if ($changes['attached'] || $changes['detached']) {
            AuditLogger::log('parent.children', "تعديل الأبناء المرتبطين بولي الأمر {$parent->name}", $parent, ['detached' => $changes['detached']], ['attached' => $changes['attached']]);
        }
    }

    private function formData(User $parent): array
    {
        return ['parent' => $parent, 'students' => Student::orderBy('full_name')->get(['id', 'full_name', 'student_number'])];
    }
}
