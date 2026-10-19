<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCriterion;
use App\Models\MemorizationRecord;
use App\Services\ProgressService;
use Tests\TestCase;

class MemorizationAndAssessmentTest extends TestCase
{
    public function test_teacher_creates_memorization_record(): void
    {
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher);
        $student = $this->studentIn($circle);
        $parent = $this->parentUser();
        $parent->children()->attach($student->id);

        $this->actingAs($teacher)->post(route('teacher.memorization.store'), [
            'student_id' => $student->id, 'recorded_on' => now()->toDateString(), 'type' => 'new',
            'surah_id' => 78, 'from_ayah' => 1, 'to_ayah' => 16, 'pages' => 0.75, 'score' => 92, 'mistakes_count' => 1,
            'notes' => 'حفظ متقن', 'recommendations' => 'مراجعة يومية', 'shared_with_parent' => 1,
        ])->assertRedirect(route('teacher.students.show', $student));

        $record = MemorizationRecord::firstOrFail();
        $this->assertSame('excellent', $record->grade);
        $this->assertSame($circle->id, $record->circle_id);
        $this->assertSame($teacher->id, $record->teacher_id);
        $this->assertSame(1, $parent->unreadNotifications()->count());
    }

    public function test_revision_record_is_stored_separately(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentIn($this->circleFor($teacher));

        $this->actingAs($teacher)->post(route('teacher.memorization.store'), [
            'student_id' => $student->id, 'recorded_on' => now()->toDateString(), 'type' => 'revision',
            'surah_id' => 114, 'from_ayah' => 1, 'to_ayah' => 6, 'pages' => 0.25, 'score' => 75,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('memorization_records', ['type' => 'revision', 'grade' => 'good']);
    }

    public function test_amount_is_required_in_quarter_pages(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentIn($this->circleFor($teacher));
        $base = ['student_id' => $student->id, 'recorded_on' => now()->toDateString(), 'type' => 'new',
            'surah_id' => 2, 'from_ayah' => 1, 'to_ayah' => 5, 'score' => 90];

        $this->actingAs($teacher)->post(route('teacher.memorization.store'), $base)->assertSessionHasErrors('pages');
        $this->actingAs($teacher)->post(route('teacher.memorization.store'), $base + ['pages' => 0.3])->assertSessionHasErrors('pages');
        $this->actingAs($teacher)->post(route('teacher.memorization.store'), $base + ['pages' => 0.5])->assertSessionHasNoErrors();

        $this->assertSame('نصف صفحة', MemorizationRecord::firstOrFail()->pagesLabel());
        $this->assertSame('ربع صفحة', MemorizationRecord::formatPages(0.25));
        $this->assertSame('صفحتان', MemorizationRecord::formatPages(2));
        $this->assertSame('3 صفحات', MemorizationRecord::formatPages(3));
        $this->assertSame('1.5 صفحة', MemorizationRecord::formatPages(1.5));
    }

    public function test_ayah_range_is_validated_against_surah(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentIn($this->circleFor($teacher));

        $this->actingAs($teacher)->post(route('teacher.memorization.store'), [
            'student_id' => $student->id, 'recorded_on' => now()->toDateString(), 'type' => 'new',
            'surah_id' => 114, 'from_ayah' => 5, 'to_ayah' => 9, 'score' => 101,
        ])->assertSessionHasErrors(['to_ayah', 'score']);

        $this->actingAs($teacher)->post(route('teacher.memorization.store'), [
            'student_id' => $student->id, 'recorded_on' => now()->toDateString(), 'type' => 'new',
            'surah_id' => 114, 'from_ayah' => 5, 'to_ayah' => 2, 'pages' => 1, 'score' => 80,
        ])->assertSessionHasErrors('to_ayah');

        $this->assertSame(0, MemorizationRecord::count());
    }

    public function test_only_passing_new_records_count_as_memorized(): void
    {
        $student = $this->studentIn(null);
        $base = ['student_id' => $student->id, 'recorded_on' => now(), 'surah_id' => 112];
        MemorizationRecord::create($base + ['type' => 'new', 'from_ayah' => 1, 'to_ayah' => 2, 'pages' => 0.5, 'score' => 90]);
        MemorizationRecord::create($base + ['type' => 'new', 'from_ayah' => 3, 'to_ayah' => 4, 'pages' => 0.5, 'score' => 50]); // failed
        MemorizationRecord::create($base + ['type' => 'revision', 'from_ayah' => 1, 'to_ayah' => 4, 'pages' => 1, 'score' => 95]);
        MemorizationRecord::create($base + ['type' => 'new', 'from_ayah' => 1, 'to_ayah' => 2, 'pages' => 0.25, 'score' => 85]); // overlap

        $progress = app(ProgressService::class)->surahProgress($student)->first();
        $this->assertSame(2, $progress['memorized']);
        $this->assertSame(4, $progress['revised']);
        $this->assertSame(50.0, $progress['percent']);

        // Amounts are measured in pages: passing new memorization only.
        $this->assertSame(0.75, app(ProgressService::class)->memorizedPages($student));
        $rows = app(\App\Services\ReportService::class)->studentRows(collect([$student]), now()->subDay(), now());
        $this->assertSame(0.75, $rows->first()['new_pages']);
        $this->assertSame(1.0, $rows->first()['revision_pages']);
    }

    public function test_teacher_records_assessment_with_admin_criteria(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentIn($this->circleFor($teacher));
        $criteria = AssessmentCriterion::active()->get();
        $scores = $criteria->mapWithKeys(fn ($c) => [$c->id => $c->max_score])->all();
        $scores[$criteria->first()->id] = intdiv($criteria->first()->max_score, 2);

        $this->actingAs($teacher)->post(route('teacher.assessments.store'), [
            'student_id' => $student->id, 'assessed_on' => now()->toDateString(), 'scores' => $scores,
            'notes' => 'مستوى جيد', 'shared_with_parent' => 1,
        ])->assertRedirect(route('teacher.students.show', $student));

        $assessment = Assessment::with('scores')->firstOrFail();
        $max = $criteria->sum('max_score');
        $expected = round(($max - $criteria->first()->max_score + intdiv($criteria->first()->max_score, 2)) / $max * 100, 2);
        $this->assertSame($expected, $assessment->percentage);
        $this->assertCount($criteria->count(), $assessment->scores);
    }

    public function test_assessment_scores_cannot_exceed_criterion_max(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentIn($this->circleFor($teacher));
        $criterion = AssessmentCriterion::active()->first();

        $this->actingAs($teacher)->post(route('teacher.assessments.store'), [
            'student_id' => $student->id, 'assessed_on' => now()->toDateString(), 'scores' => [$criterion->id => $criterion->max_score + 1],
        ])->assertSessionHasErrors("scores.{$criterion->id}");
        $this->assertSame(0, Assessment::count());
    }

    public function test_teacher_edit_is_audited(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentIn($this->circleFor($teacher));
        $record = MemorizationRecord::create(['student_id' => $student->id, 'teacher_id' => $teacher->id, 'recorded_on' => now(), 'type' => 'new', 'surah_id' => 112, 'from_ayah' => 1, 'to_ayah' => 4, 'score' => 70]);

        $this->actingAs($teacher)->put(route('teacher.memorization.update', $record), [
            'recorded_on' => now()->toDateString(), 'type' => 'new', 'surah_id' => 112, 'from_ayah' => 1, 'to_ayah' => 4, 'pages' => 0.25, 'score' => 88,
        ])->assertRedirect();

        $this->assertSame(88, $record->fresh()->score);
        $this->assertDatabaseHas('audit_logs', ['action' => 'memorization.update', 'user_id' => $teacher->id]);
    }
}
