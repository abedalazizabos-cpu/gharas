<?php

namespace App\Support;

use App\Models\PermissionRule;
use App\Models\User;

/**
 * What each role may do, editable by the super admin. Everything is allowed by default
 * (the behaviour the center started with); the super admin can switch a permission off
 * for a whole role, or allow/deny it for one user, which wins over the role.
 */
class Permissions
{
    /** role => [permission => label] */
    public const BY_ROLE = [
        User::ROLE_ADMIN => [
            'students.manage' => 'إدارة الطلاب (إضافة وتعديل ونقل وحذف)',
            'circles.manage' => 'إدارة الحلقات ومواعيدها',
            'teachers.manage' => 'إدارة المشايخ وإنذاراتهم',
            'parents.manage' => 'إدارة أولياء الأمور',
            'attendance.manage' => 'الحضور والغياب',
            'memorization.manage' => 'سجلات الحفظ والمراجعة',
            'assessments.manage' => 'التقييمات ومعاييرها',
            'archive.view' => 'السجل الأسبوعي والأرشيف',
            'reports.view' => 'التقارير وتنزيلها PDF',
            'announcements.manage' => 'الإعلانات',
            'contact.manage' => 'رسائل التواصل',
            'admins.manage' => 'حسابات المديرين',
            'visitors.manage' => 'حسابات الزوار والداعمين',
            'audit.view' => 'سجل العمليات',
        ],
        User::ROLE_TEACHER => [
            'teacher.attendance' => 'تسجيل الحضور',
            'teacher.memorization' => 'تسجيل التسميع وتعديله',
            'teacher.assessments' => 'تقييم الطلاب',
            'teacher.students.edit' => 'تعديل بيانات الطلاب وأولياء الأمور',
            'teacher.guardian.password' => 'تغيير كلمة سر ولي الأمر',
            'teacher.circle.edit' => 'تعديل اسم الحلقة وموعدها',
            'teacher.archive' => 'السجل الأسبوعي والأرشيف',
            'messages.send' => 'مراسلة أولياء الأمور',
        ],
        User::ROLE_PARENT => [
            'parent.weeks' => 'السجلات الأسبوعية السابقة',
            'messages.send' => 'مراسلة المحفظ',
        ],
        User::ROLE_VISITOR => [
            'visitor.reports' => 'التقارير الشاملة',
            'visitor.archive' => 'السجل الأسبوعي والأرشيف',
            'visitor.download' => 'تنزيل ملفات PDF',
        ],
    ];

    /** Roles whose permissions can be edited (the super admin always has everything). */
    public static function roles(): array
    {
        return array_keys(self::BY_ROLE);
    }

    public static function forRole(string $role): array
    {
        return self::BY_ROLE[$role] ?? [];
    }

    private static array $cache = [];

    public static function flush(): void
    {
        self::$cache = [];
    }

    /** Role-level switch (default on). */
    public static function roleAllows(string $role, string $permission): bool
    {
        $rules = self::$cache['role:'.$role] ??= PermissionRule::whereNull('user_id')->where('role', $role)->pluck('allowed', 'permission')->all();

        return (bool) ($rules[$permission] ?? true);
    }

    /** null = follows the role, true/false = set for this user. */
    public static function userOverride(User $user, string $permission): ?bool
    {
        $rules = self::$cache['user:'.$user->id] ??= PermissionRule::where('user_id', $user->id)->pluck('allowed', 'permission')->all();

        return array_key_exists($permission, $rules) ? (bool) $rules[$permission] : null;
    }

    public static function allows(User $user, string $permission): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if (! array_key_exists($permission, self::forRole($user->role))) {
            return false;
        }

        return self::userOverride($user, $permission) ?? self::roleAllows($user->role, $permission);
    }

    /** @param array<string, bool> $allowed permission => on/off for a role */
    public static function saveRole(string $role, array $allowed): void
    {
        foreach (self::forRole($role) as $permission => $label) {
            PermissionRule::updateOrCreate(['role' => $role, 'user_id' => null, 'permission' => $permission], ['allowed' => (bool) ($allowed[$permission] ?? false)]);
        }
        self::flush();
    }

    /** @param array<string, string> $choices permission => inherit|allow|deny */
    public static function saveUser(User $user, array $choices): void
    {
        foreach (self::forRole($user->role) as $permission => $label) {
            $choice = $choices[$permission] ?? 'inherit';
            if ($choice === 'inherit') {
                PermissionRule::where('user_id', $user->id)->where('permission', $permission)->delete();
            } else {
                PermissionRule::updateOrCreate(['role' => null, 'user_id' => $user->id, 'permission' => $permission], ['allowed' => $choice === 'allow']);
            }
        }
        self::flush();
    }
}
