<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Tests\TestCase;

class GuardianByPhoneTest extends TestCase
{
    private function payload(array $extra): array
    {
        return ['full_name' => 'يوسف خالد العتيبي', 'enrollment_date' => now()->toDateString()] + $extra;
    }

    public function test_new_mobile_creates_a_parent_account_and_links_it(): void
    {
        $this->actingAs($this->admin())->get(route('admin.students.create'))->assertOk()->assertSee('رقم جوال ولي الأمر')->assertDontSee('parent_ids[]', false);

        $this->actingAs($this->admin())->post(route('admin.students.store'), $this->payload([
            'guardian_name' => 'خالد العتيبي', 'guardian_phone' => '٠٥٥٩٨٧٦٥٤٣', 'guardian_relationship' => 'father',
        ]))->assertRedirect()->assertSessionHas('guardian_notice');

        $parent = User::where('phone', '0559876543')->firstOrFail();
        $this->assertSame(User::ROLE_PARENT, $parent->role);
        $this->assertTrue($parent->isParentOf(Student::where('full_name', 'يوسف خالد العتيبي')->first()));
        $this->assertStringContainsString('كلمة المرور المؤقتة', session('guardian_notice'));
    }

    public function test_existing_mobile_links_the_same_parent_for_siblings(): void
    {
        $parent = $this->parentUser();
        $admin = $this->admin();
        $before = User::count();

        $this->actingAs($admin)->post(route('admin.students.store'), $this->payload(['guardian_phone' => $parent->phone]))
            ->assertSessionHasNoErrors();

        $this->assertSame($before, User::count());
        $this->assertTrue($parent->isParentOf(Student::where('full_name', 'يوسف خالد العتيبي')->first()));
        $this->actingAs($this->admin())->getJson(route('admin.students.guardian-lookup', ['phone' => $parent->phone]))
            ->assertJson(['found' => true, 'parent' => true, 'name' => $parent->name]);
    }

    public function test_mobile_is_required_and_validated(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.students.store'), $this->payload([]))->assertSessionHasErrors('guardian_phone');
        $this->actingAs($admin)->post(route('admin.students.store'), $this->payload(['guardian_phone' => '12345', 'guardian_name' => 'أحمد علي']))->assertSessionHasErrors('guardian_phone');
        $this->actingAs($admin)->post(route('admin.students.store'), $this->payload(['guardian_phone' => '0551112223']))->assertSessionHasErrors('guardian_name');
        $teacher = $this->teacher();
        $teacher->update(['phone' => '0512223334']);
        $this->actingAs($admin)->post(route('admin.students.store'), $this->payload(['guardian_phone' => '0512223334', 'guardian_name' => 'أحمد علي']))->assertSessionHasErrors('guardian_phone');
        $this->assertSame(0, Student::where('full_name', 'يوسف خالد العتيبي')->count());
    }
}
