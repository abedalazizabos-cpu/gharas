<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\CircleSession;
use App\Models\MemorizationRecord;
use App\Models\WeeklyArchive;
use App\Services\WeeklyArchiveService;
use App\Support\PdfExport;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WeeklyArchiveTest extends TestCase
{
    private function setUpWeeks(): array
    {
        Storage::fake('local');
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher);
        $student = $this->studentIn($circle, ['full_name' => 'يوسف الأرشيف']);
        $lastWeek = WeeklyArchiveService::weekStart()->subWeek()->addDay(); // Monday of last week
        $session = CircleSession::create(['circle_id' => $circle->id, 'session_date' => $lastWeek->toDateString()]);
        AttendanceRecord::create(['circle_session_id' => $session->id, 'student_id' => $student->id, 'status' => 'absent']);
        MemorizationRecord::create(['student_id' => $student->id, 'circle_id' => $circle->id, 'teacher_id' => $teacher->id, 'recorded_on' => $lastWeek, 'type' => 'new', 'surah_id' => 112, 'from_ayah' => 1, 'to_ayah' => 4, 'score' => 95, 'notes' => 'سجل الأسبوع الماضي', 'shared_with_parent' => true]);
        MemorizationRecord::create(['student_id' => $student->id, 'circle_id' => $circle->id, 'teacher_id' => $teacher->id, 'recorded_on' => now(), 'type' => 'revision', 'surah_id' => 113, 'from_ayah' => 1, 'to_ayah' => 5, 'score' => 80, 'notes' => 'سجل هذا الأسبوع', 'shared_with_parent' => true]);

        return [$teacher, $circle, $student, $lastWeek];
    }

    public function test_finished_weeks_are_saved_as_files_and_listed_by_month(): void
    {
        [$teacher, $circle, , $lastWeek] = $this->setUpWeeks();

        $this->actingAs($this->admin())->get(route('admin.archive.index'))->assertOk()->assertSee($circle->name);
        $archive = WeeklyArchive::firstOrFail();
        $this->assertSame(WeeklyArchiveService::weekStart($lastWeek)->toDateString(), $archive->week_start->toDateString());
        $this->assertSame(1, $archive->records_count);
        Storage::disk('local')->assertExists($archive->path);
        $this->assertStringEndsWith('.pdf', $archive->path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($archive->path));
        $this->assertStringContainsString('سجل الأسبوع الماضي', PdfExport::lastText());
        $this->assertStringNotContainsString('سجل هذا الأسبوع', PdfExport::lastText());
        $this->assertSame(1, WeeklyArchive::count()); // the current week is not archived until it ends

        $this->actingAs($teacher)->get(route('teacher.archive.download', $archive))->assertOk()->assertDownload();
        $this->actingAs($this->teacher())->get(route('teacher.archive.download', $archive))->assertForbidden();
    }

    public function test_week_view_shows_only_that_week(): void
    {
        [$teacher, $circle, , $lastWeek] = $this->setUpWeeks();

        $this->actingAs($teacher)->get(route('teacher.dashboard'))->assertSee('الفلق (1 - 5)')->assertDontSee('الإخلاص (1 - 4)');
        $this->actingAs($teacher)->get(route('teacher.weeks.show', ['circle' => $circle->id]))->assertSee('سجل هذا الأسبوع')->assertDontSee('سجل الأسبوع الماضي');
        $this->actingAs($teacher)->get(route('teacher.weeks.show', ['circle' => $circle->id, 'week' => $lastWeek->toDateString()]))
            ->assertSee('سجل الأسبوع الماضي')->assertSee('غائب')->assertDontSee('سجل هذا الأسبوع');
    }

    public function test_parent_browses_past_weeks_of_their_child_only(): void
    {
        [, , $student, $lastWeek] = $this->setUpWeeks();
        $parent = $this->parentUser();
        $parent->children()->attach($student->id, ['relationship' => 'father']);

        $this->actingAs($parent)->get(route('parent.children.weeks', [$student, 'week' => $lastWeek->toDateString()]))
            ->assertOk()->assertSee('سجل الأسبوع الماضي')->assertSee(WeeklyArchiveService::label(WeeklyArchiveService::weekStart($lastWeek)));
        $this->actingAs($this->parentUser())->get(route('parent.children.weeks', $student))->assertForbidden();
    }

    public function test_visitor_browses_all_weeks_with_shortened_names_and_no_notes(): void
    {
        [, $circle, $student, $lastWeek] = $this->setUpWeeks();
        $visitor = \App\Models\User::factory()->visitor()->create();

        $this->actingAs($visitor)->get(route('visitor.archive.index'))->assertOk()->assertSee($circle->name);
        $archive = WeeklyArchive::firstOrFail();
        $this->actingAs($visitor)->get(route('visitor.weeks.show', ['circle' => $circle->id, 'week' => $lastWeek->toDateString()]))
            ->assertOk()->assertSee('يوسف ا.')->assertDontSee('يوسف الأرشيف')->assertDontSee('سجل الأسبوع الماضي');

        $pdf = $this->actingAs($visitor)->get(route('visitor.archive.download', $archive))->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $text = PdfExport::lastText();
        $this->assertStringContainsString('يوسف ا.', $text);
        $this->assertStringNotContainsString('يوسف الأرشيف', $text);
        $this->assertStringNotContainsString('سجل الأسبوع الماضي', $text);
        // The stored file keeps full names for the administration.
        $this->actingAs($this->admin())->get(route('admin.archive.download', $archive))->assertOk();
        $this->assertStringContainsString('يوسف الأرشيف', app(WeeklyArchiveService::class)->document($circle, app(WeeklyArchiveService::class)->week(WeeklyArchiveService::weekStart($lastWeek), $circle))[2][0]['rows']->implode(0, ' '));
        // Read-only: no write routes exist for visitors.
        $this->actingAs($visitor)->post('/visitor/archive')->assertStatus(405);
    }
}
