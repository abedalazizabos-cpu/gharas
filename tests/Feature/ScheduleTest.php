<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public function test_scheduled_tasks_run_in_process_without_proc_open(): void
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertCount(7, $events);
        // Shared hosting (Hostinger) disables proc_open, so no task may start a separate process.
        $events->each(fn ($e) => $this->assertInstanceOf(CallbackEvent::class, $e, (string) $e->description));
        $this->assertEqualsCanonicalizing(
            ['gharas:archive-weeks', 'gharas:sunnah-status', 'gharas:recording-alerts', 'gharas:honor-board', 'gharas:monthly-reports', 'gharas:backup', 'gharas:prune-offline'],
            $events->pluck('description')->all()
        );
    }

    public function test_the_scheduler_runs_a_due_task(): void
    {
        $this->travelTo(now()->setTime(0, 15));
        $this->artisan('schedule:run')->expectsOutputToContain('gharas:sunnah-status')->assertSuccessful();
    }
}
