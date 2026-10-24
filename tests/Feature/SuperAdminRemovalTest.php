<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Circle;
use App\Models\CircleSession;
use App\Models\MemorizationRecord;
use App\Models\Student;
use App\Models\User;
use App\Services\StatsService;
use Tests\TestCase;

class SuperAdminRemovalTest extends TestCase
{
    /** A circle with a student, a guardian, a record and an attendance day. */
    private function filledCircle(User $teacher, ?User $parent = null): array
    {
        $circle = $this->circleFor($teacher);
        $student = $this->studentIn($circle);
        $parent ??= $this->parentUser();
        $parent->children()->attach($student->id, ['relationship' => 'father']);
        $record = MemorizationRecord::create(['student_id' => $student->id, 'circle_id' => $circle->id, 'teacher_id' => $teacher->id, 'recorded_on' => now(), 'type' => 'new', 'surah_id' => 112, 'from_ayah' => 1, 'to_ayah' => 4, 'pages' => 0.25, 'score' => 90]);
        $session = CircleSession::create(['circle_id' => $circle->id, 'session_date' => now()->toDateString()]);
        AttendanceRecord::create(['circle_session_id' => $session->id, 'student_id' => $student->id, 'status' => 'present']);

        return compact('circle', 'student', 'parent', 'record', 'session');
    }

    public function test_only_the_super_admin_reaches_removal_and_trash(): void
    {
        $circle = $this->circleFor($this->teacher());
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.trash.index'))->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.trash.circle.remove', $circle), ['confirm' => 1])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.trash.wipe'), ['phrase' => 'حذف بيانات التجربة'])->assertForbidden();
        $this->actingAs($admin)->get(route('admin.circles.show', $circle))->assertDontSee('حذف شامل');
        $this->assertNotSoftDeleted($circle);
    }

    public function test_removing_a_circle_takes_its_students_records_guardians_and_only_teacher(): void
    {
        $super = $this->superAdmin();
        $teacher = $this->teacher();
        $busyTeacher = $this->teacher();
        $a = $this->filledCircle($teacher);
        $b = $this->filledCircle($busyTeacher);
        $this->circleFor($busyTeacher);
        // This guardian also has a child in another circle, so the account stays.
        $sharedParent = $this->parentUser();
        $sharedParent->children()->attach($a['student']->id);
        $sharedParent->children()->attach($b['student']->id);

        $this->actingAs($super)->get(route('admin.trash.circle', $a['circle']))->assertOk()
            ->assertSee('تحذير')->assertSee($teacher->name.' (يُحذف حسابه)');
        $this->actingAs($super)->delete(route('admin.trash.circle.remove', $a['circle']))->assertSessionHasErrors('confirm');
        $this->assertNotSoftDeleted($a['circle']);

        $this->actingAs($super)->delete(route('admin.trash.circle.remove', $a['circle']), ['confirm' => 1])
            ->assertRedirect(route('admin.trash.index'));

        foreach (['circle', 'student', 'parent', 'record', 'session'] as $key) {
            $this->assertSoftDeleted($a[$key]);
        }
        $this->assertSoftDeleted($teacher);
        $this->assertNotSoftDeleted($sharedParent);
        $this->assertNotSoftDeleted($b['circle']);
        $this->assertNotSoftDeleted($b['student']);

        // A teacher with another circle keeps the account.
        $this->actingAs($super)->delete(route('admin.trash.circle.remove', $b['circle']), ['confirm' => 1]);
        $this->assertNotSoftDeleted($busyTeacher);
        $this->assertSame(1, Circle::count());
    }

    public function test_a_removed_circle_comes_back_from_the_trash_with_everything(): void
    {
        $super = $this->superAdmin();
        $teacher = $this->teacher();
        $a = $this->filledCircle($teacher);
        $this->actingAs($super)->delete(route('admin.trash.circle.remove', $a['circle']), ['confirm' => 1]);

        $page = $this->actingAs($super)->get(route('admin.trash.index'))->assertOk()->assertSee($a['circle']->name);
        $at = Circle::onlyTrashed()->first()->deleted_at->toDateTimeString();
        $page->assertSee($at, false);

        $this->actingAs($super)->post(route('admin.trash.restore'), ['at' => $at])->assertSessionHas('success');
        foreach (['circle', 'student', 'parent', 'record', 'session'] as $key) {
            $this->assertNotSoftDeleted($a[$key]);
        }
        $this->assertNotSoftDeleted($teacher);
        $this->actingAs($super)->get(route('admin.trash.index'))->assertSee('السلة فارغة');
    }

    public function test_purge_deletes_a_batch_for_good(): void
    {
        $super = $this->superAdmin();
        $a = $this->filledCircle($this->teacher());
        $this->actingAs($super)->delete(route('admin.trash.circle.remove', $a['circle']), ['confirm' => 1]);
        $at = Circle::onlyTrashed()->first()->deleted_at->toDateTimeString();

        $this->actingAs($super)->delete(route('admin.trash.purge'), ['at' => $at])->assertSessionHas('success');

        $this->assertDatabaseMissing('circles', ['id' => $a['circle']->id]);
        $this->assertDatabaseMissing('students', ['id' => $a['student']->id]);
        $this->assertDatabaseMissing('users', ['id' => $a['parent']->id]);
        $this->assertSame(0, AttendanceRecord::count());
        // The same circle code can now be used again.
        $this->assertSame(0, Circle::withTrashed()->where('code', $a['circle']->code)->count());
    }

    public function test_wipe_clears_the_trial_data_and_keeps_the_super_admin(): void
    {
        $super = $this->superAdmin();
        $admin = $this->admin();
        $visitor = User::factory()->visitor()->create();
        $this->filledCircle($this->teacher());
        $this->filledCircle($this->teacher());
        $loose = $this->studentIn(null);

        $this->actingAs($super)->post(route('admin.trash.wipe'), ['phrase' => 'خطأ'])->assertSessionHasErrors('phrase');
        $this->assertSame(2, Circle::count());

        $this->actingAs($super)->post(route('admin.trash.wipe'), ['phrase' => 'حذف بيانات التجربة'])
            ->assertRedirect(route('admin.trash.index'));

        $this->assertSame(0, Circle::count());
        $this->assertSame(0, Student::count());
        $this->assertSame(0, MemorizationRecord::count());
        $this->assertSame([$super->id], User::pluck('id')->all());
        $this->assertSoftDeleted($admin);
        $this->assertSoftDeleted($visitor);
        $this->assertSoftDeleted($loose);
        $summary = app(StatsService::class)->adminSummary();
        $this->assertSame(0, $summary['present_today']);
        $this->assertSame(0, $summary['students_total']);
        $this->actingAs($super)->get(route('admin.dashboard'))->assertOk();

        // One batch holds the whole wipe, so it can be undone in one step.
        $this->actingAs($super)->get(route('admin.trash.index'))->assertSee('الحلقات (2)');
        $this->actingAs($super)->delete(route('admin.trash.purge-all'));
        $this->assertSame(1, User::withTrashed()->count());
        $this->assertSame(0, Student::withTrashed()->count());
    }

    public function test_super_admin_suspends_and_reactivates_admins_from_the_list(): void
    {
        $super = $this->superAdmin();
        $admin = $this->admin();

        $this->actingAs($super)->get(route('admin.admins.index'))->assertOk()
            ->assertSee(route('admin.access.users.edit', $admin), false)->assertSee('إيقاف');
        $this->actingAs($super)->patch(route('admin.admins.status', $admin), ['status' => 'suspended'])->assertSessionHas('success');
        $this->assertSame('suspended', $admin->fresh()->status);
        $this->actingAs($super)->patch(route('admin.admins.status', $admin), ['status' => 'active']);
        $this->assertSame('active', $admin->fresh()->status);

        // Admin staff see no trash or permissions links (super admin pages stay hidden).
        $this->actingAs($admin)->get(route('admin.admins.index'))->assertOk()
            ->assertDontSee('سلة المحذوفات')->assertDontSee(route('admin.access.users.edit', $admin), false);
    }
}
