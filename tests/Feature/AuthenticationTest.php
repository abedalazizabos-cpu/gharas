<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_login_page_is_arabic_and_reachable(): void
    {
        $this->get('/login')->assertOk()->assertSee('تسجيل الدخول')->assertSee('dir="rtl"', false);
    }

    public function test_each_role_is_redirected_to_its_dashboard(): void
    {
        foreach (['admin' => '/admin', 'teacher' => '/teacher', 'parent' => '/parent', 'visitor' => '/visitor'] as $role => $path) {
            $user = User::factory()->{$role}()->create();
            $this->post('/login', ['login' => $user->email, 'password' => 'password123'])->assertRedirect($path);
            $this->get($path)->assertOk();
            $this->post('/logout')->assertRedirect('/');
            $this->assertGuest();
        }
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = $this->admin();
        $this->from('/login')->post('/login', ['login' => $user->email, 'password' => 'wrong-pass1'])
            ->assertRedirect('/login')->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_suspended_account_cannot_log_in(): void
    {
        $user = User::factory()->teacher()->suspended()->create();
        $this->post('/login', ['login' => $user->email, 'password' => 'password123'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_user_suspended_mid_session_is_logged_out(): void
    {
        $user = $this->teacher();
        $this->actingAs($user)->get('/teacher')->assertOk();
        $user->forceFill(['status' => 'suspended'])->save();
        $this->get('/teacher')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_guests_are_redirected_from_protected_pages(): void
    {
        foreach (['/dashboard', '/admin', '/admin/students', '/teacher', '/parent', '/messages', '/profile'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    public function test_there_is_no_public_registration(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'x', 'email' => 'x@x.com', 'password' => 'password123'])->assertNotFound();
    }

    public function test_password_reset_link_can_be_requested(): void
    {
        $user = $this->parentUser();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('success');
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_roles_cannot_open_other_role_areas(): void
    {
        $this->actingAs($this->teacher())->get('/admin')->assertForbidden();
        $this->actingAs($this->parentUser())->get('/teacher')->assertForbidden();
        $this->actingAs($this->parentUser())->get('/admin/students')->assertForbidden();
        $this->actingAs($this->admin())->get('/parent')->assertForbidden();
    }

    public function test_role_cannot_be_mass_assigned_through_profile(): void
    {
        $parent = $this->parentUser();
        $this->actingAs($parent)->put('/profile', ['name' => 'اسم جديد', 'email' => $parent->email, 'phone' => $parent->phone, 'role' => 'admin', 'status' => 'active'])
            ->assertSessionHas('success');
        $this->assertSame('parent', $parent->fresh()->role);
        $this->assertSame('اسم جديد', $parent->fresh()->name);
    }
}
