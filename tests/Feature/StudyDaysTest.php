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
}
