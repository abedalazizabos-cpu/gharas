<?php

namespace Tests\Feature;

use App\Models\MemorizationRecord;
use Tests\TestCase;

class TeacherAccessTest extends TestCase
{
    public function test_teacher_cannot_open_a_circle_they_do_not_teach(): void
    {
        $teacher = $this->teacher();
        $other = $this->circleFor($this->teacher());

        $this->actingAs($teacher)->get(route('teacher.circles.show', $other))->assertForbidden();
        $this->actingAs($teacher)->get(route('teacher.attendance.create', $other))->assertForbidden();
        $this->actingAs($teacher)->post(route('teacher.attendance.store', $other), ['date' => now()->toDateString(), 'attendance' => []])->assertForbidden();
    }

    public function test_teacher_cannot_view_or_record_for_students_outside_their_circles(): void
    {
        $teacher = $this->teacher();
        $outsider = $this->studentIn($this->circleFor($this->teacher()));

        $this->actingAs($teacher)->get(route('teacher.students.show', $outsider))->assertForbidden();
        $this->actingAs($teacher)->post(route('teacher.memorization.store'), [
            'student_id' => $outsider->id, 'recorded_on' => now()->toDateString(), 'type' => 'new',
            'surah_id' => 114, 'from_ayah' => 1, 'to_ayah' => 6, 'score' => 90,
        ])->assertForbidden();
        $this->assertSame(0, MemorizationRecord::count());
    }

    public function test_teacher_sees_own_circles_only_in_list(): void
    {
        $teacher = $this->teacher();
        $mine = $this->circleFor($teacher, ['name' => 'حلقتي الخاصة']);
        $this->circleFor($this->teacher(), ['name' => 'حلقة زميل آخر']);

        $this->actingAs($teacher)->get(route('teacher.circles.index'))->assertSee('حلقتي الخاصة')->assertDontSee('حلقة زميل آخر');
    }

    public function test_teacher_cannot_delete_records_or_create_accounts(): void
    {
        $teacher = $this->teacher();
        $this->actingAs($teacher)->post(route('admin.teachers.store'), [])->assertForbidden();
        $this->actingAs($teacher)->post(route('admin.admins.store'), [])->assertForbidden();
        $record = MemorizationRecord::create([
            'student_id' => $this->studentIn($this->circleFor($teacher))->id, 'teacher_id' => $teacher->id,
            'recorded_on' => now(), 'type' => 'new', 'surah_id' => 114, 'from_ayah' => 1, 'to_ayah' => 6, 'score' => 80,
        ]);
        $this->actingAs($teacher)->delete(route('admin.memorization.destroy', $record))->assertForbidden();
        $this->assertNotSoftDeleted($record);
    }

    public function test_teacher_edit_window_is_enforced(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentIn($this->circleFor($teacher));
        $record = MemorizationRecord::create([
            'student_id' => $student->id, 'teacher_id' => $teacher->id, 'recorded_on' => now()->subDays(20),
            'type' => 'new', 'surah_id' => 114, 'from_ayah' => 1, 'to_ayah' => 6, 'score' => 80,
        ]);
        $record->forceFill(['created_at' => now()->subDays(20)])->save();

        $this->actingAs($teacher)->get(route('teacher.memorization.edit', $record))->assertForbidden();
    }

    public function test_teacher_can_start_a_message_to_parents_of_their_students_only(): void
    {
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher);
        $student = $this->studentIn($circle);
        $parent = $this->parentUser();
        $parent->children()->attach($student->id);
        $stranger = $this->parentUser();
        $stranger->children()->attach($this->studentIn($this->circleFor($this->teacher()))->id);

        $this->actingAs($teacher)->get(route('teacher.circles.show', $circle))
            ->assertSee(e(route('messages.create', ['to' => $parent->id, 'student' => $student->id])), false);

        $this->actingAs($teacher)->post(route('messages.store'), ['recipient_id' => $stranger->id, 'subject' => 'متابعة', 'body' => 'نص'])
            ->assertSessionHasErrors('recipient_id');
        $this->actingAs($teacher)->post(route('messages.store'), ['recipient_id' => $parent->id, 'student_id' => $student->id, 'subject' => 'متابعة الحفظ', 'body' => 'نص الرسالة'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('messages', ['sender_id' => $teacher->id, 'recipient_id' => $parent->id]);
        $this->assertSame(1, $parent->unreadNotifications()->count());
    }
}
