<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\CircleSession;
use App\Models\MemorizationRecord;
use App\Models\Setting;
use App\Models\Student;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    public function test_admin_can_create_student_with_circle_and_parent(): void
    {
        $admin = $this->admin();
        $circle = $this->circleFor($this->teacher());
        $parent = $this->parentUser();

        $this->actingAs($admin)->post(route('admin.students.store'), [
            'full_name' => 'عمر أحمد السبيعي', 'enrollment_date' => now()->toDateString(),
            'circle_id' => $circle->id, 'parent_ids' => [$parent->id],
        ])->assertRedirect();

        $student = Student::where('full_name', 'عمر أحمد السبيعي')->firstOrFail();
        $this->assertSame($circle->id, $student->current_circle_id);
        $this->assertNotEmpty($student->student_number);
        $this->assertTrue($parent->isParentOf($student));
        $this->assertDatabaseHas('circle_enrollments', ['student_id' => $student->id, 'circle_id' => $circle->id, 'left_at' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.create']);
    }

    public function test_admin_can_update_student(): void
    {
        $student = $this->studentIn(null);
        $this->actingAs($this->admin())->put(route('admin.students.update', $student), [
            'full_name' => 'اسم معدل', 'student_number' => $student->student_number, 'enrollment_date' => '2026-01-01',
        ])->assertRedirect(route('admin.students.show', $student));

        $this->assertSame('اسم معدل', $student->fresh()->full_name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.update']);
    }

    public function test_transfer_keeps_history_and_old_records(): void
    {
        $from = $this->circleFor($this->teacher());
        $to = $this->circleFor($this->teacher());
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.students.store'), ['full_name' => 'طالب منقول', 'enrollment_date' => now()->toDateString(), 'circle_id' => $from->id, 'guardian_phone' => '0557778889', 'guardian_name' => 'ولي أمر منقول']);
        $student = Student::where('full_name', 'طالب منقول')->first();

        $session = CircleSession::create(['circle_id' => $from->id, 'session_date' => now()->toDateString()]);
        AttendanceRecord::create(['circle_session_id' => $session->id, 'student_id' => $student->id, 'status' => 'present']);
        MemorizationRecord::create(['student_id' => $student->id, 'circle_id' => $from->id, 'recorded_on' => now(), 'type' => 'new', 'surah_id' => 112, 'from_ayah' => 1, 'to_ayah' => 4, 'score' => 90]);

        $this->actingAs($admin)->post(route('admin.students.transfer', $student), ['circle_id' => $to->id, 'reason' => 'ترقية'])->assertSessionHas('success');

        $student->refresh();
        $this->assertSame($to->id, $student->current_circle_id);
        $this->assertSame(2, $student->enrollments()->count());
        $this->assertNotNull($student->enrollments()->where('circle_id', $from->id)->first()->left_at);
        $this->assertSame(1, $student->attendanceRecords()->count());
        $this->assertSame(1, $student->memorizationRecords()->where('circle_id', $from->id)->count());
    }

    public function test_transfer_to_full_circle_is_blocked_without_override(): void
    {
        $full = $this->circleFor($this->teacher(), ['capacity' => 1]);
        $this->studentIn($full);
        $student = $this->studentIn(null);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.students.transfer', $student), ['circle_id' => $full->id])->assertSessionHasErrors('circle_id');
        $this->assertNull($student->fresh()->current_circle_id);

        $this->actingAs($admin)->post(route('admin.students.transfer', $student), ['circle_id' => $full->id, 'override_capacity' => 1])->assertSessionHas('success');
        $this->assertSame($full->id, $student->fresh()->current_circle_id);

        Setting::put('allow_capacity_override', '0');
        $another = $this->studentIn(null);
        $this->actingAs($admin)->post(route('admin.students.transfer', $another), ['circle_id' => $full->id, 'override_capacity' => 1])->assertSessionHasErrors('circle_id');
    }

    public function test_withdrawing_a_student_leaves_circle_but_keeps_data(): void
    {
        $circle = $this->circleFor($this->teacher());
        $student = $this->studentIn($circle);

        $this->actingAs($this->admin())->patch(route('admin.students.status', $student), ['status' => 'withdrawn'])->assertSessionHas('success');

        $student->refresh();
        $this->assertSame('withdrawn', $student->status);
        $this->assertNull($student->current_circle_id);
        $this->assertNotNull($student->withdrawn_at);
    }

    public function test_deleting_a_student_is_soft_and_keeps_records(): void
    {
        $student = $this->studentIn(null);
        MemorizationRecord::create(['student_id' => $student->id, 'recorded_on' => now(), 'type' => 'new', 'surah_id' => 112, 'from_ayah' => 1, 'to_ayah' => 4, 'score' => 90]);

        $this->actingAs($this->admin())->delete(route('admin.students.destroy', $student))->assertRedirect();

        $this->assertSoftDeleted($student);
        $this->assertSame(1, MemorizationRecord::where('student_id', $student->id)->count());
    }

    public function test_invalid_student_data_is_rejected(): void
    {
        $existing = $this->studentIn(null);
        $this->actingAs($this->admin())->post(route('admin.students.store'), [
            'full_name' => '', 'student_number' => $existing->student_number, 'birth_date' => now()->addYear()->toDateString(),
            'circle_id' => 9999, 'phone' => 'abc',
        ])->assertSessionHasErrors(['full_name', 'student_number', 'birth_date', 'circle_id', 'phone', 'enrollment_date']);
    }

    public function test_admin_can_search_and_filter_students(): void
    {
        $circle = $this->circleFor($this->teacher());
        $this->studentIn($circle, ['full_name' => 'يوسف الحربي']);
        $this->studentIn(null, ['full_name' => 'زياد العتيبي', 'status' => 'suspended']);

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.students.index', ['q' => 'يوسف']))->assertSee('يوسف الحربي')->assertDontSee('زياد العتيبي');
        $this->actingAs($admin)->get(route('admin.students.index', ['status' => 'suspended']))->assertSee('زياد العتيبي')->assertDontSee('يوسف الحربي');
        $this->actingAs($admin)->get(route('admin.students.index', ['circle' => $circle->id]))->assertSee('يوسف الحربي')->assertDontSee('زياد العتيبي');
    }
}
