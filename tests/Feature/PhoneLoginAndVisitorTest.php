<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Tests\TestCase;

class PhoneLoginAndVisitorTest extends TestCase
{
    public function test_parent_logs_in_with_mobile_number_in_any_common_format(): void
    {
        $parent = User::factory()->parent()->create(['email' => null, 'phone' => '0551234567']);

        foreach (['0551234567', '055 123 4567', '٠٥٥١٢٣٤٥٦٧', '+966551234567'] as $typed) {
            $this->post('/login', ['login' => $typed, 'password' => 'password123'])->assertRedirect('/parent');
            $this->post('/logout');
        }
        $this->post('/login', ['login' => '0559999999', 'password' => 'password123'])->assertSessionHasErrors('login');
        $this->assertGuest();
        $this->assertSame('0551234567', $parent->fresh()->phone);
    }

    public function test_parent_account_requires_a_unique_mobile_and_email_is_optional(): void
    {
        $admin = $this->admin();
        User::factory()->parent()->create(['phone' => '0551111111']);
        $data = ['name' => 'ولي أمر جديد', 'status' => 'active', 'password' => 'secret123', 'password_confirmation' => 'secret123'];

        $this->actingAs($admin)->post(route('admin.parents.store'), $data)->assertSessionHasErrors('phone');
        $this->actingAs($admin)->post(route('admin.parents.store'), $data + ['phone' => '055 111 1111'])->assertSessionHasErrors('phone');
        $this->actingAs($admin)->post(route('admin.parents.store'), $data + ['phone' => '0552222222'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['phone' => '0552222222', 'email' => null, 'role' => 'parent']);
    }

    public function test_visitor_sees_all_circles_and_reports_with_masked_student_names(): void
    {
        $visitor = User::factory()->visitor()->create();
        $circle = $this->circleFor($this->teacher(), ['name' => 'حلقة الاختبار']);
        $student = $this->studentIn($circle, ['full_name' => 'يوسف أحمد السبيعي']);

        $this->actingAs($visitor)->get(route('visitor.dashboard'))->assertOk()->assertSee('حلقة الاختبار');
        $this->actingAs($visitor)->get(route('visitor.reports.center'))->assertOk()->assertSee('حلقة الاختبار');
        $this->actingAs($visitor)->get(route('visitor.reports.circle', $circle))->assertOk()
            ->assertSee('يوسف أ.')->assertDontSee('يوسف أحمد السبيعي')->assertDontSee($student->student_number);
        $this->assertSame('يوسف أحمد السبيعي', $student->fresh()->full_name);
    }

    public function test_visitor_cannot_change_anything(): void
    {
        $visitor = User::factory()->visitor()->create();
        $circle = $this->circleFor($this->teacher());
        $student = $this->studentIn($circle);

        $this->actingAs($visitor)->get(route('admin.students.index'))->assertForbidden();
        $this->actingAs($visitor)->get(route('admin.reports.student', $student))->assertForbidden();
        $this->actingAs($visitor)->get(route('teacher.circles.index'))->assertForbidden();
        $this->actingAs($visitor)->post(route('admin.students.store'), ['full_name' => 'x'])->assertForbidden();
        $this->actingAs($visitor)->post(route('teacher.attendance.store', $circle), [])->assertForbidden();
        $this->actingAs($visitor)->post(route('messages.store'), ['recipient_id' => 1, 'subject' => 'x', 'body' => 'x'])->assertForbidden();
        $this->actingAs($visitor)->delete(route('admin.circles.destroy', $circle))->assertForbidden();
        $this->assertSame(0, Message::count());
        $this->assertNotNull($circle->fresh());
    }

    public function test_admin_manages_visitor_accounts(): void
    {
        $this->actingAs($this->admin())->post(route('admin.visitors.store'), [
            'name' => 'وفد زائر', 'email' => 'guest@example.com', 'status' => 'active', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('admin.visitors.index'));
        $this->assertDatabaseHas('users', ['email' => 'guest@example.com', 'role' => 'visitor']);
        $this->get(route('admin.visitors.index'))->assertOk()->assertSee('وفد زائر');
    }
}
