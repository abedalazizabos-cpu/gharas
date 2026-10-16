<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    public function test_only_the_super_admin_manages_roles_permissions_and_settings(): void
    {
        $admin = $this->admin();
        foreach (['admin.access.roles', 'admin.access.users', 'admin.settings.edit'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertForbidden();
        }
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('الأدوار والصلاحيات')->assertDontSee(route('admin.settings.edit'));

        $super = $this->superAdmin();
        $this->actingAs($super)->get(route('admin.dashboard'))->assertOk()->assertSee('الأدوار والصلاحيات');
        $this->actingAs($super)->get(route('admin.access.roles'))->assertOk()->assertSee('مراسلة أولياء الأمور');
        $this->actingAs($super)->get(route('admin.settings.edit'))->assertOk()->assertSee('يبدأ الظهور من الساعة')->assertSee('الشعار والهوية');
        $this->actingAs($super)->get(route('admin.students.index'))->assertOk();
    }

    public function test_switching_a_role_permission_off_blocks_the_page_and_hides_the_menu(): void
    {
        $super = $this->superAdmin();
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.reports.index'))->assertOk();

        $this->actingAs($super)->put(route('admin.access.roles.update'), ['perm' => ['admin' => ['reports.view' => '0', 'students.manage' => '1']]])->assertSessionHasNoErrors();
        Permissions::flush();

        $this->actingAs($admin)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.students.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertDontSee(route('admin.reports.index'));
        // The super admin is never limited.
        $this->actingAs($super)->get(route('admin.reports.index'))->assertOk();
    }

    public function test_user_override_wins_over_the_role(): void
    {
        $super = $this->superAdmin();
        $teacher = $this->teacher();
        $student = $this->studentIn($this->circleFor($teacher));
        $parent = $this->parentUser();
        $student->parents()->attach($parent->id);

        // Role: teachers may not change guardian passwords...
        $this->actingAs($super)->put(route('admin.access.roles.update'), ['perm' => ['teacher' => collect(Permissions::forRole('teacher'))->map(fn () => '1')->merge(['teacher.guardian.password' => '0'])->all()]]);
        Permissions::flush();
        $this->actingAs($teacher)->put(route('teacher.students.guardians.update', [$student, $parent]), ['name' => $parent->name, 'phone' => $parent->phone, 'password' => 'abcdef1'])->assertForbidden();
        $this->actingAs($teacher)->get(route('teacher.students.show', $student))->assertDontSee('كلمة سر جديدة');

        // ...but this teacher is allowed personally.
        $this->actingAs($super)->put(route('admin.access.users.update', $teacher), ['role' => 'teacher', 'status' => 'active', 'perm' => ['teacher.guardian.password' => 'allow', 'teacher.circle.edit' => 'deny']])->assertSessionHasNoErrors();
        Permissions::flush();
        $this->actingAs($teacher)->put(route('teacher.students.guardians.update', [$student, $parent]), ['name' => $parent->name, 'phone' => $parent->phone, 'password' => 'abcdef1'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('abcdef1', $parent->fresh()->password));
        $this->actingAs($teacher)->get(route('teacher.circles.edit', $student->circle))->assertForbidden();
    }

    public function test_super_admin_changes_a_users_role_with_safety_checks(): void
    {
        $super = $this->superAdmin();
        $visitor = User::factory()->visitor()->create();

        $this->actingAs($super)->put(route('admin.access.users.update', $visitor), ['role' => 'admin', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame('admin', $visitor->fresh()->role);
        $this->actingAs($visitor->fresh())->get(route('admin.dashboard'))->assertOk();

        $teacher = $this->teacher();
        $this->circleFor($teacher);
        $this->actingAs($super)->put(route('admin.access.users.update', $teacher), ['role' => 'admin', 'status' => 'active'])->assertSessionHas('error');
        $this->assertSame('teacher', $teacher->fresh()->role);

        $this->actingAs($super)->put(route('admin.access.users.update', $super), ['role' => 'admin', 'status' => 'active'])->assertSessionHas('error');
        $this->assertTrue($super->fresh()->isSuperAdmin());

        $this->actingAs($this->admin())->put(route('admin.access.users.update', $visitor), ['role' => 'superadmin', 'status' => 'active'])->assertForbidden();
    }

    public function test_reminder_schedule_and_audience_come_from_settings(): void
    {
        \App\Models\Setting::put('reminders_roles', 'teacher');
        \App\Models\Setting::put('reminders_from', '07:00');
        \App\Models\Setting::put('reminders_duration', '30');
        $this->actingAs($this->parentUser())->get(route('parent.dashboard'))->assertDontSee('reminders-data', false);
        $this->actingAs($this->teacher())->get(route('teacher.dashboard'))->assertSee('reminders-data', false)->assertSee('"from":"07:00"', false)->assertSee('"duration":30', false);
    }
}
