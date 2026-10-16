<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Shared account helpers for teacher, parent and admin management. */
trait ManagesAccounts
{
    protected function createAccount(array $data, string $role): User
    {
        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
        ]);
        $user->role = $role;
        $user->status = $data['status'];
        $user->email_verified_at = now();
        $user->save();

        AuditLogger::log('user.create', "إنشاء حساب {$user->roleLabel()}: {$user->name}", $user, [], $user->only(['name', 'email', 'phone', 'role']));

        return $user;
    }

    protected function updateAccount(User $user, array $data): void
    {
        $user->fill(collect($data)->only(['name', 'email', 'phone'])->all());
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->status = $data['status'];
        $user->save();

        AuditLogger::logChanges('user.update', "تعديل حساب {$user->roleLabel()}: {$user->name}", $user);
    }

    protected function toggleStatus(Request $request, User $user)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(User::STATUSES))]]);
        if ($user->is($request->user())) {
            return back()->with('error', 'لا يمكنك تغيير حالة حسابك الشخصي.');
        }
        $old = $user->status;
        $user->status = $data['status'];
        $user->save();
        AuditLogger::log('user.status', "تغيير حالة حساب {$user->name}", $user, ['status' => $old], ['status' => $user->status]);

        return back()->with('success', 'تم تحديث حالة الحساب إلى «'.$user->statusLabel().'».');
    }

    protected function ensureRole(User $user, string $role): void
    {
        abort_unless($user->role === $role, 404);
    }
}
