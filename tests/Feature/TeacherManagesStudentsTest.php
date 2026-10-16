<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeacherManagesStudentsTest extends TestCase
{
    public function test_teacher_edits_own_student_and_links_guardian_by_mobile(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentIn($this->circleFor($teacher), ['full_name' => 'سالم الأول', 'student_number' => 'S-777', 'internal_notes' => 'سري']);

        $this->actingAs($teacher)->get(route('teacher.students.edit', $student))->assertOk()->assertSee('رقم جوال ولي الأمر')->assertDontSee('ملاحظات الإدارة');
        $this->actingAs($teacher)->put(route('teacher.students.update', $student), [
            'full_name' => 'سالم المعدل', 'enrollment_date' => $student->enrollment_date->toDateString(),
            'student_number' => 'HACK', 'internal_notes' => 'تغيير',
            'guardian_phone' => '0551112223', 'guardian_name' => 'والد سالم',
        ])->assertRedirect(route('teacher.students.show', $student))->assertSessionHas('guardian_notice');

        $student->refresh();
        $this->assertSame('سالم المعدل', $student->full_name);
        $this->assertSame('S-777', $student->student_number);
        $this->assertSame('سري', $student->internal_notes);
        $this->assertTrue(User::where('phone', '0551112223')->firstOrFail()->isParentOf($student));
    }

    public function test_teacher_changes_guardian_data_and_password(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentIn($this->circleFor($teacher));
        $parent = $this->parentUser();
        $student->parents()->attach($parent->id, ['relationship' => 'father']);

        $this->actingAs($teacher)->get(route('teacher.students.show', $student))->assertOk()->assertSee('البيانات وكلمة السر');
        $this->actingAs($teacher)->put(route('teacher.students.guardians.update', [$student, $parent]), [
            'name' => 'اسم جديد', 'phone' => '0553334445', 'password' => 'newpass99',
        ])->assertSessionHasNoErrors()->assertSessionHas('guardian_notice');

        $parent->refresh();
        $this->assertSame('اسم جديد', $parent->name);
        $this->assertSame('0553334445', $parent->phone);
        $this->assertTrue(Hash::check('newpass99', $parent->password));
        $this->assertStringContainsString('newpass99', session('guardian_notice'));

        // The parent can now sign in with the new mobile and password.
        auth()->logout();
        $this->post(route('login'), ['login' => '0553334445', 'password' => 'newpass99'])->assertRedirect();
        $this->assertAuthenticatedAs($parent);
    }

    public function test_teacher_cannot_touch_students_or_guardians_outside_their_circles(): void
    {
        $teacher = $this->teacher();
        $other = $this->studentIn($this->circleFor($this->teacher()));
        $parent = $this->parentUser();
        $other->parents()->attach($parent->id);
        $mine = $this->studentIn($this->circleFor($teacher));
        $before = $parent->password;

        $this->actingAs($teacher)->get(route('teacher.students.edit', $other))->assertForbidden();
        $this->actingAs($teacher)->put(route('teacher.students.update', $other), ['full_name' => 'xxx yyy', 'enrollment_date' => now()->toDateString()])->assertForbidden();
        $this->actingAs($teacher)->put(route('teacher.students.guardians.update', [$other, $parent]), ['name' => 'abc', 'phone' => $parent->phone, 'password' => 'hacked1'])->assertForbidden();
        // A parent not linked to the teacher's own student is not reachable through it either.
        $this->actingAs($teacher)->put(route('teacher.students.guardians.update', [$mine, $parent]), ['name' => 'abc', 'phone' => $parent->phone, 'password' => 'hacked1'])->assertNotFound();
        $this->assertSame($before, $parent->fresh()->password);
    }

    public function test_admin_can_also_reset_guardian_password_from_student_page(): void
    {
        $student = $this->studentIn($this->circleFor($this->teacher()));
        $parent = $this->parentUser();
        $student->parents()->attach($parent->id);

        $this->actingAs($this->admin())->get(route('admin.students.show', $student))->assertOk()->assertSee('البيانات وكلمة السر');
        $this->actingAs($this->admin())->put(route('admin.students.guardians.update', [$student, $parent]), ['name' => $parent->name, 'phone' => $parent->phone, 'password' => '12345678'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('12345678', $parent->fresh()->password));
    }

    public function test_typing_an_existing_fathers_mobile_moves_the_child_to_his_account(): void
    {
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher);
        $father = $this->parentUser();
        $brother = $this->studentIn($circle, ['full_name' => 'الأخ الأكبر']);
        $brother->parents()->attach($father->id, ['relationship' => 'father']);
        // Same father entered again with a misspelled name and another number.
        $typo = $this->parentUser();
        $child = $this->studentIn($circle, ['full_name' => 'الأخ الأصغر']);
        $child->parents()->attach($typo->id, ['relationship' => 'father']);

        $this->actingAs($teacher)->put(route('teacher.students.guardians.update', [$child, $typo]), ['name' => 'أي اسم', 'phone' => $father->phone])
            ->assertSessionHasNoErrors()->assertSessionHas('guardian_notice');

        $this->assertTrue($father->isParentOf($child));
        $this->assertFalse($typo->fresh()->isParentOf($child));
        $this->assertSame($father->name, $father->fresh()->name);

        // The father now sees both children on his dashboard.
        $this->actingAs($father)->get(route('parent.dashboard'))->assertSee('الأخ الأكبر')->assertSee('الأخ الأصغر');
    }
}
