<?php

namespace Tests\Feature;

use App\Models\MemorizationRecord;
use Tests\TestCase;

class ParentAccessTest extends TestCase
{
    public function test_parent_can_view_own_child_only(): void
    {
        $parent = $this->parentUser();
        $circle = $this->circleFor($this->teacher());
        $child = $this->studentIn($circle, ['full_name' => 'ابني المسجل']);
        $stranger = $this->studentIn($circle, ['full_name' => 'طالب غريب']);
        $parent->children()->attach($child->id, ['relationship' => 'father']);

        $this->actingAs($parent)->get(route('parent.dashboard'))->assertOk()->assertSee('ابني المسجل')->assertDontSee('طالب غريب');
        $this->actingAs($parent)->get(route('parent.children.show', $child))->assertOk();
        $this->actingAs($parent)->get(route('parent.children.show', $stranger))->assertForbidden();
    }

    public function test_parent_does_not_see_unshared_notes_or_internal_notes(): void
    {
        $parent = $this->parentUser();
        $teacher = $this->teacher();
        $child = $this->studentIn($this->circleFor($teacher), ['internal_notes' => 'ملاحظة إدارية سرية']);
        $parent->children()->attach($child->id);

        foreach ([[true, 'ملاحظة مشتركة'], [false, 'ملاحظة داخلية للمحفظ']] as [$shared, $note]) {
            MemorizationRecord::create([
                'student_id' => $child->id, 'teacher_id' => $teacher->id, 'recorded_on' => now(), 'type' => 'new',
                'surah_id' => 114, 'from_ayah' => 1, 'to_ayah' => 6, 'score' => 95, 'notes' => $note, 'shared_with_parent' => $shared,
            ]);
        }

        $this->actingAs($parent)->get(route('parent.children.show', $child))
            ->assertSee('ملاحظة مشتركة')->assertDontSee('ملاحظة داخلية للمحفظ')->assertDontSee('ملاحظة إدارية سرية');
    }

    public function test_parent_cannot_modify_attendance_or_records(): void
    {
        $parent = $this->parentUser();
        $circle = $this->circleFor($this->teacher());
        $child = $this->studentIn($circle);
        $parent->children()->attach($child->id);

        $this->actingAs($parent)->post(route('teacher.attendance.store', $circle), [])->assertForbidden();
        $this->actingAs($parent)->post(route('teacher.memorization.store'), ['student_id' => $child->id])->assertForbidden();
        $this->actingAs($parent)->post(route('admin.attendance.store', $circle), [])->assertForbidden();
    }

    public function test_parent_can_only_message_teachers_of_their_children(): void
    {
        $parent = $this->parentUser();
        $teacher = $this->teacher();
        $otherTeacher = $this->teacher();
        $child = $this->studentIn($this->circleFor($teacher));
        $parent->children()->attach($child->id);

        $this->actingAs($parent)->post(route('messages.store'), ['recipient_id' => $otherTeacher->id, 'subject' => 'سؤال', 'body' => 'نص'])
            ->assertSessionHasErrors('recipient_id');
        $this->actingAs($parent)->post(route('messages.store'), ['recipient_id' => $teacher->id, 'student_id' => $child->id, 'subject' => 'سؤال', 'body' => 'نص الرسالة'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('messages', ['sender_id' => $parent->id, 'recipient_id' => $teacher->id]);
        $this->assertSame(1, $teacher->unreadNotifications()->count());
    }
}
