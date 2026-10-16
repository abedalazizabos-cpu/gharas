<?php

namespace Tests\Feature;

use Tests\TestCase;

class TeacherEditsCircleTest extends TestCase
{
    public function test_teacher_renames_own_circle_and_changes_its_time_only(): void
    {
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher, ['name' => 'حلقة قديمة', 'code' => 'C-321', 'capacity' => 15]);

        $this->actingAs($teacher)->get(route('teacher.circles.edit', $circle))->assertOk()->assertSee('موعد الحلقة');
        $this->actingAs($teacher)->put(route('teacher.circles.update', $circle), [
            'name' => 'حلقة الفجر الجديدة', 'time_type' => 'prayer', 'prayer' => 'fajr', 'time_note' => 'لمدة ساعة',
            'code' => 'HACK', 'capacity' => 99, 'teacher_id' => null,
        ])->assertRedirect(route('teacher.circles.show', $circle))->assertSessionHasNoErrors();

        $circle->refresh();
        $this->assertSame('حلقة الفجر الجديدة', $circle->name);
        $this->assertSame('prayer', $circle->time_type);
        $this->assertSame('fajr', $circle->prayer);
        $this->assertSame('C-321', $circle->code);
        $this->assertSame(15, (int) $circle->capacity);
        $this->assertSame($teacher->id, $circle->teacher_id);
        $this->assertStringContainsString('الفجر', $circle->timeLabel());

        $this->actingAs($teacher)->put(route('teacher.circles.update', $circle), ['name' => 'x', 'time_type' => 'clock', 'start_time' => '17:00', 'end_time' => '16:00'])
            ->assertSessionHasErrors(['end_time']);
    }

    public function test_teacher_cannot_edit_another_teachers_circle(): void
    {
        $teacher = $this->teacher();
        $other = $this->circleFor($this->teacher(), ['name' => 'حلقة زميل']);

        $this->actingAs($teacher)->get(route('teacher.circles.edit', $other))->assertForbidden();
        $this->actingAs($teacher)->put(route('teacher.circles.update', $other), ['name' => 'مخترقة', 'time_type' => 'custom', 'time_note' => 'الضحى'])->assertForbidden();
        $this->assertSame('حلقة زميل', $other->fresh()->name);
    }
}
