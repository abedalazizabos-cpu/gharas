<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\TeacherWarning;
use Tests\TestCase;

class TeacherControlTest extends TestCase
{
    public function test_admin_replaces_or_removes_a_circles_teacher(): void
    {
        $old = $this->teacher();
        $new = $this->teacher();
        $circle = $this->circleFor($old);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.circles.show', $circle))->assertOk()->assertSee('محفظ الحلقة');
        $this->actingAs($admin)->get(route('admin.circles.index'))->assertOk()->assertSee('teacherModal', false);

        $this->actingAs($admin)->post(route('admin.circles.teacher', $circle), ['teacher_id' => $new->id])->assertSessionHas('success');
        $this->assertSame($new->id, $circle->fresh()->teacher_id);
        $this->assertSame(1, $new->notifications()->count());
        $this->assertSame(1, $old->notifications()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'circle.teacher']);

        $this->actingAs($admin)->post(route('admin.circles.teacher', $circle), ['teacher_id' => ''])->assertSessionHas('success');
        $this->assertNull($circle->fresh()->teacher_id);

        // Only teachers can be assigned.
        $this->actingAs($admin)->post(route('admin.circles.teacher', $circle), ['teacher_id' => $this->parentUser()->id])->assertSessionHasErrors('teacher_id');
    }

    public function test_admin_moves_all_circles_and_can_delete_the_teacher(): void
    {
        $leaving = $this->teacher();
        $target = $this->teacher();
        $a = $this->circleFor($leaving);
        $b = $this->circleFor($leaving);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.teachers.show', $leaving))->assertOk()->assertSee('نقل حلقات المحفظ');
        $this->actingAs($admin)->post(route('admin.teachers.reassign', $leaving), ['target_id' => $target->id, 'delete_account' => 1])
            ->assertRedirect(route('admin.teachers.index'));

        $this->assertSame($target->id, $a->fresh()->teacher_id);
        $this->assertSame($target->id, $b->fresh()->teacher_id);
        $this->assertSoftDeleted('users', ['id' => $leaving->id]);

        $this->actingAs($admin)->post(route('admin.teachers.reassign', $target), ['target_id' => $target->id])->assertSessionHasErrors('target_id');
    }

    public function test_teacher_receives_and_acknowledges_a_warning(): void
    {
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.teachers.warn', $teacher), ['level' => 'first', 'reason' => 'التأخر المتكرر', 'circle_id' => $circle->id])
            ->assertSessionHas('success');
        $warning = TeacherWarning::firstOrFail();
        $this->assertSame(1, $teacher->notifications()->count());
        $this->actingAs($admin)->get(route('admin.teachers.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.teachers.show', $teacher))->assertSee('لم يطلع بعد');

        $this->actingAs($teacher)->get(route('teacher.dashboard'))->assertSee('التأخر المتكرر')->assertSee('إنذار أول');
        $this->actingAs($teacher)->post(route('teacher.warnings.acknowledge', $warning))->assertSessionHas('success');
        $this->assertNotNull($warning->fresh()->acknowledged_at);
        $this->actingAs($teacher)->get(route('teacher.dashboard'))->assertDontSee(route('teacher.warnings.acknowledge', $warning));

        // Another teacher cannot acknowledge it; teachers cannot issue warnings.
        $other = $this->teacher();
        $this->actingAs($other)->post(route('teacher.warnings.acknowledge', $warning))->assertForbidden();
        $this->actingAs($other)->post(route('admin.teachers.warn', $teacher), ['level' => 'final', 'reason' => 'x'])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.teachers.warn', $teacher), ['level' => 'bad'])->assertSessionHasErrors(['level', 'reason']);
    }

    public function test_reminders_show_for_signed_in_users_and_can_be_turned_off(): void
    {
        $this->assertNotEmpty(Setting::reminders());
        $this->actingAs($this->parentUser())->get(route('parent.dashboard'))->assertSee('reminders-data', false);

        Setting::put('reminders_enabled', '0');
        $this->actingAs($this->parentUser())->get(route('parent.dashboard'))->assertDontSee('reminders-data', false);

        Setting::put('reminders_text', "سبحان الله | ذكر\n\nالحمد لله");
        $this->assertSame([['text' => 'سبحان الله', 'source' => 'ذكر'], ['text' => 'الحمد لله', 'source' => '']], Setting::reminders());
    }
}
