<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesAccounts;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserAccountRequest;
use App\Models\User;
use App\Services\AuditLogger;

/** Read-only accounts for external delegations and supporters. */
class VisitorController extends Controller
{
    use ManagesAccounts;

    public function index()
    {
        return view('admin.visitors.index', ['visitors' => User::role(User::ROLE_VISITOR)->orderBy('name')->paginate(15)]);
    }

    public function create()
    {
        return view('admin.visitors.form', ['visitor' => new User(['status' => 'active'])]);
    }

    public function store(UserAccountRequest $request)
    {
        $this->createAccount($request->validated(), User::ROLE_VISITOR);

        return redirect()->route('admin.visitors.index')->with('success', 'تم إنشاء حساب الزائر بنجاح.');
    }

    public function edit(User $visitor)
    {
        $this->ensureRole($visitor, User::ROLE_VISITOR);

        return view('admin.visitors.form', compact('visitor'));
    }

    public function update(UserAccountRequest $request, User $visitor)
    {
        $this->ensureRole($visitor, User::ROLE_VISITOR);
        $this->updateAccount($visitor, $request->validated());

        return redirect()->route('admin.visitors.index')->with('success', 'تم تحديث حساب الزائر بنجاح.');
    }

    public function destroy(User $visitor)
    {
        $this->ensureRole($visitor, User::ROLE_VISITOR);
        $visitor->delete();
        AuditLogger::log('user.delete', "حذف حساب الزائر {$visitor->name}", $visitor);

        return back()->with('success', 'تم حذف حساب الزائر.');
    }
}
