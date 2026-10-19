<?php

namespace Tests\Feature;

use App\Models\TeacherWarning;
use App\Services\AuditLogger;
use Tests\TestCase;

class SuperAdminPrivacyTest extends TestCase
{
    public function test_admin_does_not_see_super_admin_activity_or_name(): void
    {
        $super = $this->superAdmin();
        $admin = $this->admin();
        $teacher = $this->teacher();

        $this->actingAs($super);
        AuditLogger::log('auth.login', "تسجيل دخول {$super->name}", $super);
        AuditLogger::log('circle.update', 'عملية سرية من المدير الأعلى');
        $this->actingAs($admin);
        AuditLogger::log('auth.login', 'تسجيل دخول المدير الإداري', $admin);
        TeacherWarning::create(['teacher_id' => $teacher->id, 'issued_by' => $super->id, 'level' => 'notice', 'reason' => 'تنبيه تجريبي']);

        $this->actingAs($admin)->get(route('admin.audit.index'))->assertOk()
            ->assertSee('تسجيل دخول المدير الإداري')->assertDontSee('عملية سرية من المدير الأعلى')->assertDontSee($super->name);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('عملية سرية من المدير الأعلى');
        $this->actingAs($admin)->get(route('admin.teachers.show', $teacher))->assertOk()
            ->assertSee('إدارة المركز')->assertDontSee($super->name);

        $this->actingAs($super)->get(route('admin.audit.index'))->assertOk()
            ->assertSee('عملية سرية من المدير الأعلى')->assertSee('تسجيل دخول المدير الإداري');
    }
}
