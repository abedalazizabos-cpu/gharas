<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Circle;
use App\Models\CircleDayOff;
use App\Models\CircleSession;
use App\Models\MemorizationRecord;
use App\Models\Student;
use App\Models\User;
use App\Services\RecordingGapService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecordingGapsTest extends TestCase
{
    private User $teacher;

    private Circle $circle;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        // Wednesday; the circle meets Saturday to Thursday and started on Saturday 2026-11-07.
        Carbon::setTestNow('2026-11-11 12:00:00');
        $this->teacher = $this->teacher();
        $this->circle = $this->circleFor($this->teacher, ['days' => Circle::STUDY_DAYS, 'status' => 'active']);
        $this->student = $this->studentIn($this->circle, ['status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function record(string $date, string $status = 'present', bool $recitation = true): void
    {
        $session = CircleSession::create(['circle_id' => $this->circle->id, 'teacher_id' => $this->teacher->id, 'session_date' => $date]);
        AttendanceRecord::create(['circle_session_id' => $session->id, 'student_id' => $this->student->id, 'status' => $status, 'recorded_by' => $this->teacher->id]);
        if ($recitation) {
            MemorizationRecord::create(['student_id' => $this->student->id, 'circle_id' => $this->circle->id, 'teacher_id' => $this->teacher->id,
                'recorded_on' => $date, 'type' => 'new', 'surah_id' => 114, 'from_ayah' => 1, 'to_ayah' => 6, 'pages' => 0.25, 'score' => 90, 'shared_with_parent' => true]);
        }
    }

    private function gaps()
    {
        return app(RecordingGapService::class)->gaps()->map(fn ($g) => $g['date']->toDateString().':'.RecordingGapService::missingLabel($g))->all();
    }

    public function test_missing_days_are_found_and_friday_today_and_all_absent_days_are_not_counted(): void
    {
        $this->record('2026-11-07');                     // Saturday: complete
        $this->record('2026-11-08', recitation: false);  // Sunday: attendance only
        // Monday 2026-11-09: nothing recorded
        $this->record('2026-11-10', 'absent', false);    // Tuesday: everyone absent, no recitation expected
        // Friday 2026-11-06 is before the first meeting and off anyway; today is not over yet.

        $this->assertSame(['2026-11-09:الحضور والتسميع', '2026-11-08:التسميع'], $this->gaps());
    }

    public function test_a_day_off_or_a_center_holiday_is_not_counted(): void
    {
        $this->record('2026-11-07');
        $this->actingAs($this->teacher)->post(route('teacher.day-offs.store', $this->circle), ['date' => '2026-11-08', 'reason' => 'مرض المحفظ'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post(route('admin.day-offs.store'), ['date' => '2026-11-09', 'date_to' => '2026-11-10', 'reason' => 'عطلة رسمية'])->assertSessionHasNoErrors();

        $this->assertSame([], $this->gaps());
        $this->assertSame(1, CircleDayOff::where('circle_id', $this->circle->id)->count());
        $this->assertSame(2, CircleDayOff::whereNull('circle_id')->count());

        // Another teacher cannot mark this circle.
        $this->actingAs($this->teacher())->post(route('teacher.day-offs.store', $this->circle), ['date' => '2026-11-10', 'reason' => 'عطلة رسمية'])->assertForbidden();

        // Cancelling the holiday brings its days back.
        $this->actingAs($this->admin())->delete(route('admin.day-offs.destroy', CircleDayOff::whereNull('circle_id')->first()));
        $this->assertSame(['2026-11-09:الحضور والتسميع'], $this->gaps());
    }

    public function test_teacher_and_admin_see_the_alert_and_get_a_morning_notification(): void
    {
        $this->record('2026-11-07');
        $admin = $this->admin();

        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertOk()
            ->assertSee('أيام لم تسجّل فيها الحضور أو التسميع')->assertSee('2026/11/10')
            ->assertSee(route('teacher.attendance.create', [$this->circle, 'date' => '2026-11-10']), false);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('محفظون لم يسجّلوا')->assertSee($this->teacher->name);
        $this->actingAs($admin)->get(route('admin.recording-gaps.index'))->assertOk()->assertSee($this->circle->name)->assertSee('2026/11/09');
        $this->actingAs($this->teacher)->get(route('admin.recording-gaps.index'))->assertForbidden();

        $this->artisan('gharas:recording-alerts')->assertSuccessful();
        $this->assertSame(1, $this->teacher->notifications()->count());
        $this->assertStringContainsString($this->circle->name, $this->teacher->notifications()->first()->data['body']);
        $this->assertSame(1, $admin->notifications()->count());
        // Running it again the same day sends nothing new.
        $this->artisan('gharas:recording-alerts')->assertSuccessful();
        $this->assertSame(1, $this->teacher->notifications()->count());
    }

    public function test_recording_the_missed_day_clears_the_alert(): void
    {
        foreach (['2026-11-07', '2026-11-08', '2026-11-09'] as $d) {
            $this->record($d);
        }
        $this->assertSame(['2026-11-10:الحضور والتسميع'], $this->gaps());
        $this->actingAs($this->teacher)->get(route('teacher.memorization.create', ['date' => '2026-11-10']))->assertOk()->assertSee('2026-11-10');

        $this->record('2026-11-10');
        $this->assertSame([], $this->gaps());
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertOk()->assertDontSee('أيام لم تسجّل فيها الحضور أو التسميع');
    }

    public function test_teacher_announces_no_class_today_and_parents_are_told(): void
    {
        $parent = $this->parentUser();
        $this->student->parents()->attach($parent->id, ['relationship' => 'أب']);
        $this->record('2026-11-07');

        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertOk()->assertSee('لا تحفيظ اليوم؟')->assertSee('ظرف في مكان الحلقة');
        $this->actingAs($this->teacher)->post(route('teacher.day-offs.store', $this->circle), ['date' => '2026-11-11', 'reason' => 'ظرف في مكان الحلقة'])->assertSessionHasNoErrors();

        $this->assertSame(1, $parent->notifications()->count());
        $this->assertSame('لا تحفيظ اليوم', $parent->notifications()->first()->data['title']);
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertOk()->assertSee('لا تحفيظ اليوم: ظرف في مكان الحلقة');
        $this->actingAs($this->admin())->get(route('admin.recording-gaps.index', ['from' => '2026-11-07', 'to' => '2026-11-10']))->assertOk()->assertSee('ظرف في مكان الحلقة');
    }
}
