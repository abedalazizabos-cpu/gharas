<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PermissionRule;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Super admin: what each role may do, and each user's role, status and personal permissions. */
class AccessController extends Controller
{
    public function roles()
    {
        return view('admin.access.roles', [
            'matrix' => collect(Permissions::BY_ROLE)->map(fn ($perms, $role) => collect($perms)->map(fn ($label, $key) => [
                'label' => $label, 'on' => Permissions::roleAllows($role, $key),
            ])),
            'counts' => User::query()->selectRaw('role, count(*) as c')->groupBy('role')->pluck('c', 'role'),
        ]);
    }

    public function updateRoles(Request $request)
    {
        foreach (Permissions::roles() as $role) {
            Permissions::saveRole($role, collect($request->input("perm.$role", []))->map(fn ($v) => (bool) $v)->all());
        }
        AuditLogger::log('access.roles', 'تعديل صلاحيات الأدوار');

        return back()->with('success', 'تم حفظ صلاحيات الأدوار. يسري التغيير فوراً على كل المستخدمين.');
    }

    public function users(Request $request)
    {
        $users = User::query()
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('email', 'like', "%{$t}%")->orWhere('phone', 'like', "%{$t}%")))
            ->when($request->query('role'), fn ($q, $r) => $q->where('role', $r))
            ->withCount('permissionRules')
            ->orderByRaw("CASE role WHEN 'superadmin' THEN 0 WHEN 'admin' THEN 1 WHEN 'teacher' THEN 2 WHEN 'visitor' THEN 3 ELSE 4 END")->orderBy('name')
            ->paginate(20)->withQueryString();

        return view('admin.access.users', ['users' => $users]);
    }

    public function editUser(User $user)
    {
        return view('admin.access.user', [
            'user' => $user,
            'perms' => collect(Permissions::forRole($user->role))->map(fn ($label, $key) => [
                'label' => $label,
                'override' => Permissions::userOverride($user, $key),
                'role' => Permissions::roleAllows($user->role, $key),
            ]),
        ]);
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'status' => ['required', Rule::in(array_keys(User::STATUSES))],
            'perm' => ['nullable', 'array'],
            'perm.*' => ['in:inherit,allow,deny'],
        ]);
        $me = $request->user();
        $newRole = $data['role'];

        if ($user->is($me) && ($newRole !== User::ROLE_SUPERADMIN || $data['status'] !== 'active')) {
            return back()->with('error', 'لا يمكنك تغيير دورك أو إيقاف حسابك بنفسك، حتى لا يبقى النظام بلا مدير أعلى.');
        }
        if ($newRole !== $user->role) {
            if ($user->isTeacher() && $user->circles()->exists()) {
                return back()->with('error', 'لهذا المحفظ حلقات مسندة إليه. انقل حلقاته إلى محفظ آخر أولاً من صفحة المشايخ.');
            }
            if ($user->isParent() && $user->children()->exists()) {
                return back()->with('error', 'هذا الحساب مرتبط بأبناء. انقل الأبناء إلى ولي أمر آخر أولاً.');
            }
            if ($newRole === User::ROLE_PARENT && ! $user->phone) {
                return back()->with('error', 'ولي الأمر يدخل برقم جواله، فأضف رقم الجوال للحساب أولاً.');
            }
            if ($newRole !== User::ROLE_PARENT && ! $user->email && ! $user->phone) {
                return back()->with('error', 'أضف بريداً أو جوالاً للحساب أولاً.');
            }
        }

        $oldRole = $user->role;
        $user->role = $newRole;
        $user->status = $data['status'];
        $user->save();
        if ($newRole === User::ROLE_TEACHER && ! $user->teacherProfile()->exists()) {
            $user->teacherProfile()->create([]);
        }
        if ($newRole === User::ROLE_PARENT && ! $user->parentProfile()->exists()) {
            $user->parentProfile()->create([]);
        }

        if ($oldRole !== $newRole) {
            // Personal permissions belonged to the old role.
            PermissionRule::where('user_id', $user->id)->delete();
            Permissions::flush();
        } else {
            Permissions::saveUser($user, $data['perm'] ?? []);
        }

        AuditLogger::log('access.user', "تعديل دور وصلاحيات {$user->name}", $user, ['role' => $oldRole], ['role' => $newRole, 'status' => $user->status]);

        return redirect()->route('admin.access.users.edit', $user)->with('success', $oldRole !== $newRole
            ? "تم تغيير دور {$user->name} إلى «{$user->roleLabel()}». يمكنك الآن ضبط صلاحياته الخاصة."
            : "تم حفظ صلاحيات {$user->name}.");
    }
}
