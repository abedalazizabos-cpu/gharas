<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\CircleSession;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    public function test_teacher_records_attendance_without_duplicates(): void
    {
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher);
        $a = $this->studentIn($circle);
        $b = $this->studentIn($circle);
        $date = now()->toDateString();

        $payload = ['date' => $date, 'attendance' => [$a->id => ['status' => 'present'], $b->id => ['status' => 'absent', 'notes' => 'مريض']]];
        $this->actingAs($teacher)->post(route('teacher.attendance.store', $circle), $payload)->assertSessionHas('success');
        $this->assertSame(2, AttendanceRecord::count());

        // Saving the same day again updates instead of duplicating.
        $payload['attendance'][$b->id] = ['status' => 'excused', 'notes' => 'عذر طبي'];
        $this->actingAs($teacher)->post(route('teacher.attendance.store', $circle), $payload)->assertSessionHas('success');

        $this->assertSame(1, CircleSession::count());
        $this->assertSame(2, AttendanceRecord::count());
        $this->assertSame('excused', AttendanceRecord::where('student_id', $b->id)->value('status'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.update']);
    }

    public function test_attendance_ignores_students_from_other_circles(): void
    {
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher);
        $outsider = $this->studentIn($this->circleFor($this->teacher()));

        $this->actingAs($teacher)->post(route('teacher.attendance.store', $circle), [
            'date' => now()->toDateString(), 'attendance' => [$outsider->id => ['status' => 'present']],
        ]);
        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_absence_notifies_linked_parent(): void
    {
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher);
        $student = $this->studentIn($circle);
        $parent = $this->parentUser();
        $parent->children()->attach($student->id);

        $this->actingAs($teacher)->post(route('teacher.attendance.store', $circle), [
            'date' => now()->toDateString(), 'attendance' => [$student->id => ['status' => 'absent']],
        ]);

        $this->assertSame(1, $parent->unreadNotifications()->count());
        $this->assertSame('تنبيه غياب', $parent->unreadNotifications()->first()->data['title']);
    }

    public function test_invalid_attendance_is_rejected(): void
    {
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher);
        $student = $this->studentIn($circle);

        $this->actingAs($teacher)->post(route('teacher.attendance.store', $circle), [
            'date' => now()->addDay()->toDateString(), 'attendance' => [$student->id => ['status' => 'unknown']],
        ])->assertSessionHasErrors(['date', "attendance.{$student->id}.status"]);
        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_admin_attendance_report_counts_real_records(): void
    {
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher);
        $s1 = $this->studentIn($circle);
        $s2 = $this->studentIn($circle);
        $this->actingAs($teacher)->post(route('teacher.attendance.store', $circle), [
            'date' => now()->toDateString(), 'attendance' => [$s1->id => ['status' => 'present'], $s2->id => ['status' => 'absent']],
        ]);

        $this->actingAs($this->admin())->get(route('admin.attendance.index'))
            ->assertOk()->assertViewHas('counts', ['present' => 1, 'late' => 0, 'absent' => 1, 'excused' => 0])->assertViewHas('rate', 50.0);
    }
}
