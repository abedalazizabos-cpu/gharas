<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Circle;
use App\Models\User;
use Tests\TestCase;

class CircleAndStatsTest extends TestCase
{
    public function test_admin_creates_circle(): void
    {
        $teacher = $this->teacher();
        $this->actingAs($this->admin())->post(route('admin.circles.store'), [
            'name' => 'حلقة الفجر', 'code' => 'C-900', 'teacher_id' => $teacher->id, 'days' => [0, 2],
            'start_time' => '16:00', 'end_time' => '17:00', 'capacity' => 10, 'status' => 'active', 'is_public' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('circles', ['code' => 'C-900', 'teacher_id' => $teacher->id]);
    }

    public function test_admin_may_give_a_teacher_overlapping_circles(): void
    {
        $teacher = $this->teacher();
        $this->circleFor($teacher, ['name' => 'حلقة قائمة', 'days' => [0, 1], 'start_time' => '16:00', 'end_time' => '17:30']);

        $this->actingAs($this->admin())->post(route('admin.circles.store'), [
            'name' => 'حلقة متداخلة', 'code' => 'C-901', 'teacher_id' => $teacher->id, 'days' => [1],
            'start_time' => '17:00', 'end_time' => '18:00', 'capacity' => 10, 'status' => 'active',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('circles', ['code' => 'C-901', 'time_type' => 'clock']);
    }

    public function test_circle_can_start_after_a_prayer_or_at_a_described_time(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.circles.store'), [
            'name' => 'حلقة العصر', 'code' => 'C-903', 'days' => [0], 'time_type' => 'prayer', 'prayer' => 'asr',
            'time_note' => 'لمدة ساعة', 'start_time' => '10:00', 'end_time' => '11:00', 'capacity' => 10, 'status' => 'active',
        ])->assertSessionHasNoErrors();
        $asr = Circle::where('code', 'C-903')->first();
        $this->assertNull($asr->start_time); // clock fields of other kinds are dropped
        $this->assertSame('بعد صلاة العصر · لمدة ساعة', $asr->timeLabel());

        $this->actingAs($admin)->post(route('admin.circles.store'), [
            'name' => 'حلقة الضحى', 'code' => 'C-904', 'days' => [6], 'time_type' => 'custom', 'time_note' => 'الضحى',
            'capacity' => 10, 'status' => 'active',
        ])->assertSessionHasNoErrors();
        $this->assertSame('الضحى', Circle::where('code', 'C-904')->first()->timeLabel());

        // Each kind requires its own detail.
        $this->actingAs($admin)->post(route('admin.circles.store'), [
            'name' => 'ناقصة', 'code' => 'C-905', 'days' => [0], 'time_type' => 'prayer', 'capacity' => 10, 'status' => 'active',
        ])->assertSessionHasErrors('prayer');
        $this->actingAs($admin)->post(route('admin.circles.store'), [
            'name' => 'ناقصة', 'code' => 'C-906', 'days' => [0], 'time_type' => 'clock', 'capacity' => 10, 'status' => 'active',
        ])->assertSessionHasErrors(['start_time', 'end_time']);
    }

    public function test_circle_with_students_cannot_be_deleted(): void
    {
        $circle = $this->circleFor($this->teacher());
        $this->studentIn($circle);
        $this->actingAs($this->admin())->delete(route('admin.circles.destroy', $circle))->assertSessionHas('error');
        $this->assertNotSoftDeleted($circle);
    }

    public function test_dashboard_statistics_come_from_database(): void
    {
        $admin = $this->admin();
        $teacher = $this->teacher();
        $active = $this->circleFor($teacher);
        $this->circleFor(null, ['status' => 'inactive']);
        $this->studentIn($active);
        $this->studentIn($active);
        $this->studentIn(null, ['status' => 'withdrawn']);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $summary = $response->viewData('summary');
        $this->assertSame(3, $summary['students_total']);
        $this->assertSame(2, $summary['students_active']);
        $this->assertSame(1, $summary['teachers']);
        $this->assertSame(2, $summary['circles_total']);
        $this->assertSame(1, $summary['circles_active']);
        $this->assertNull($summary['attendance_rate_today']);

        $this->studentIn($active);
        $this->assertSame(4, $this->actingAs($admin)->get(route('admin.dashboard'))->viewData('summary')['students_total']);
    }

    public function test_public_home_shows_live_counts_without_personal_data(): void
    {
        $circle = $this->circleFor($this->teacher(), ['name' => 'حلقة عامة']);
        $this->studentIn($circle, ['full_name' => 'اسم طالب خاص']);
        $a = new Announcement(['title' => 'إعلان للعامة', 'body' => 'نص', 'audience' => 'public']);
        $a->save();
        $b = new Announcement(['title' => 'إعلان داخلي للمحفظين', 'body' => 'نص', 'audience' => 'teachers']);
        $b->save();

        $response = $this->get('/')->assertOk()->assertSee('حلقة عامة')->assertSee('إعلان للعامة')
            ->assertDontSee('اسم طالب خاص')->assertDontSee('إعلان داخلي للمحفظين');
        $this->assertSame(['students' => 1, 'teachers' => 1, 'circles' => 1], $response->viewData('stats'));
    }

    public function test_relationships_are_wired_correctly(): void
    {
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher);
        $student = $this->studentIn($circle);
        $parent = $this->parentUser();
        $parent->children()->attach($student->id);

        $this->assertTrue($circle->teacher->is($teacher));
        $this->assertTrue($teacher->circles->first()->is($circle));
        $this->assertTrue($circle->students->first()->is($student));
        $this->assertTrue($student->parents->first()->is($parent));
        $this->assertTrue($parent->children->first()->is($student));
        $this->assertTrue($teacher->teachesStudent($student));
        $this->assertInstanceOf(User::class, $student->circle->teacher);
        $this->assertInstanceOf(Circle::class, $student->circle);
    }

    public function test_announcements_respect_audience_and_dates(): void
    {
        $parent = $this->parentUser();
        foreach ([['للأولياء', 'parents', null], ['للمحفظين فقط', 'teachers', null], ['منتهٍ', 'all', now()->subDay()]] as [$title, $audience, $expires]) {
            $a = new Announcement(['title' => $title, 'body' => 'نص', 'audience' => $audience, 'publish_at' => now()->subDays(3), 'expires_at' => $expires]);
            $a->save();
        }

        $this->actingAs($parent)->get(route('parent.announcements'))->assertSee('للأولياء')->assertDontSee('للمحفظين فقط')->assertDontSee('منتهٍ');
    }

    public function test_super_admin_uploads_and_removes_logo(): void
    {
        $this->actingAs($this->admin())->get(route('admin.settings.edit'))->assertForbidden();
        $admin = $this->superAdmin();
        $settings = collect(\App\Models\Setting::DEFAULTS)->except('center_logo')->all();
        $settings['reminders_days'] = explode(',', $settings['reminders_days']);
        $settings['reminders_roles'] = explode(',', $settings['reminders_roles']);

        $this->actingAs($admin)->put(route('admin.settings.update'), $settings + ['logo' => \Illuminate\Http\UploadedFile::fake()->image('logo.png', 200, 200)])
            ->assertSessionHasNoErrors();
        $logo = \App\Models\Setting::get('center_logo');
        $this->assertNotNull($logo);
        $this->assertFileExists(public_path($logo));
        $this->get(route('home'))->assertSee(asset($logo), false);

        $this->actingAs($admin)->put(route('admin.settings.update'), $settings + ['remove_logo' => '1'])->assertSessionHasNoErrors();
        $this->assertNull(\App\Models\Setting::get('center_logo'));
        $this->assertFileDoesNotExist(public_path($logo));
    }
}
