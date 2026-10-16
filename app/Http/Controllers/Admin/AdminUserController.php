<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesAccounts;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserAccountRequest;
use App\Models\User;
use App\Services\AuditLogger;

/** Admin accounts can only be created here, by an existing admin. */
class AdminUserController extends Controller
{
    use ManagesAccounts;

    public function index()
    {
        return view('admin.admins.index', ['admins' => User::role(User::ROLE_ADMIN)->orderBy('name')->paginate(15)]);
    }

    public function create()
    {
        return view('admin.admins.form', ['admin' => new User(['status' => 'active'])]);
    }

    public function store(UserAccountRequest $request)
    {
        $this->createAccount($request->validated(), User::ROLE_ADMIN);

        return redirect()->route('admin.admins.index')->with('success', 'تم إنشاء حساب المدير بنجاح.');
    }

    public function edit(User $admin)
    {
        $this->ensureRole($admin, User::ROLE_ADMIN);

        return view('admin.admins.form', compact('admin'));
    }

    public function update(UserAccountRequest $request, User $admin)
    {
        $this->ensureRole($admin, User::ROLE_ADMIN);
        $data = $request->validated();
        if ($admin->is($request->user())) {
            $data['status'] = 'active';
        }
        $this->updateAccount($admin, $data);

        return redirect()->route('admin.admins.index')->with('success', 'تم تحديث حساب المدير بنجاح.');
    }

    public function destroy(User $admin)
    {
        $this->ensureRole($admin, User::ROLE_ADMIN);
        if ($admin->is(auth()->user())) {
            return back()->with('error', 'لا يمكنك حذف حسابك الشخصي.');
        }
        if (User::admins()->active()->count() <= 1) {
            return back()->with('error', 'يجب أن يبقى مدير نشط واحد على الأقل.');
        }
        $admin->delete();
        AuditLogger::log('user.delete', "حذف حساب المدير {$admin->name}", $admin);

        return back()->with('success', 'تم حذف حساب المدير.');
    }
}
