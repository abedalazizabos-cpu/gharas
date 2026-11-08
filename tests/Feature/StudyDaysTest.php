<?php

namespace Tests\Feature;

use App\Models\Circle;
use Tests\TestCase;

class StudyDaysTest extends TestCase
{
    public function test_circles_study_saturday_to_thursday_and_friday_is_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.circles.create'))->assertOk()->assertSee('الجمعة إجازة')->assertDontSee('id="day-5"', false);

        $base = ['name' => 'حلقة الأيام', 'code' => 'C-950', 'teacher_id' => $this->teacher()->id, 'time_type' => 'custom', 'time_note' => 'بعد العصر', 'capacity' => 10, 'status' => 'active'];
        $this->actingAs($admin)->post(route('admin.circles.store'), $base + ['days' => [5]])->assertSessionHasErrors('days.0');
        $this->actingAs($admin)->post(route('admin.circles.store'), $base + ['days' => Circle::STUDY_DAYS])->assertSessionHasNoErrors();

        $this->assertSame('من السبت إلى الخميس', Circle::where('code', 'C-950')->firstOrFail()->daysLabel());
    }

    public function test_study_week_runs_saturday_to_thursday(): void
    {
        $friday = \Illuminate\Support\Carbon::parse('2026-11-06');
        $start = \App\Services\WeeklyArchiveService::weekStart($friday->toDateString());
        $this->assertTrue($start->isSaturday());
        $this->assertSame('2026-10-31', $start->toDateString());
        $this->assertSame('2026-10-31', \App\Services\WeeklyArchiveService::weekStart('2026-11-05')->toDateString());
        $this->assertSame('2026-11-07', \App\Services\WeeklyArchiveService::weekStart('2026-11-07')->toDateString());
        $this->assertSame('من السبت 2026/10/31 إلى الخميس 2026/11/05', \App\Services\WeeklyArchiveService::label($start));

        $w = app(\App\Services\WeeklyArchiveService::class)->week($start);
        $this->assertSame(['2026-10-31', '2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05'], $w['days']->map->toDateString()->all());
    }
}
