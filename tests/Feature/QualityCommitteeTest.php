<?php

namespace Tests\Feature;

use App\Models\CombinedTestStage;
use App\Models\MemorizationRecord;
use App\Models\QualityCriterion;
use App\Models\QualityTest;
use App\Models\Student;
use App\Models\User;
use App\Services\QualityService;
use App\Support\Juz;
use Tests\TestCase;

class QualityCommitteeTest extends TestCase
{
    private function newRecord(Student $student, int $surah, int $from = 1, int $to = 5): void
    {
        MemorizationRecord::create([
            'student_id' => $student->id, 'circle_id' => $student->current_circle_id, 'recorded_on' => now()->toDateString(),
            'type' => 'new', 'surah_id' => $surah, 'from_ayah' => $from, 'to_ayah' => $to, 'pages' => 1, 'score' => 90,
        ]);
    }

    private function examiner(): User
    {
        $t = $this->teacher();
        $t->forceFill(['is_quality_examiner' => true])->save();

        return $t;
    }

    public function test_juz_boundaries(): void
    {
        $this->assertSame(30, Juz::of(114, 1));
        $this->assertSame(30, Juz::of(78, 1));
        $this->assertSame(29, Juz::of(67, 1));
        $this->assertSame(29, Juz::of(77, 50));
        $this->assertSame(28, Juz::of(58, 1));
        $this->assertSame(1, Juz::of(2, 141));
        $this->assertSame(2, Juz::of(2, 142));
    }

    public function test_default_ladder_follows_the_center_order(): void
    {
        $ladder = CombinedTestStage::active()->get()->map(fn ($s) => $s->from_juz.'-'.$s->to_juz)->all();
        $this->assertSame(['30-28', '30-26', '25-23', '25-21', '30-21', '20-18', '20-16', '15-13', '15-11', '20-11', '10-8', '10-6', '5-3', '5-1', '10-1', '30-1'], $ladder);
    }

    public function test_moving_to_the_next_juz_without_a_test_raises_an_alert_until_passed(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentIn($this->circleFor($teacher));
        $service = app(QualityService::class);

        $this->newRecord($student, 114);
        $this->assertSame([], $service->forStudent($student)['missing_individual']);

        // Started Al-Mulk: juz 30 is finished but untested.
        $this->newRecord($student, 67);
        $status = $service->forStudent($student);
        $this->assertSame(29, $status['current_juz']);
        $this->assertSame([30], array_keys($status['missing_individual']));

        $this->actingAs($teacher)->get(route('teacher.dashboard'))->assertOk()->assertSee('دون اختبار الجودة')->assertSee($student->full_name);
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('دون اختبار الجودة');
        $this->actingAs($admin)->get(route('admin.quality.index'))->assertOk()->assertSee($student->full_name);

        // Admin sends the student to an examiner who is not his circle teacher.
        $examiner = $this->examiner();
        $this->actingAs($admin)->post(route('admin.quality.assign'), ['student_id' => $student->id, 'kind' => 'individual', 'juz' => 30, 'examiner_id' => $examiner->id])
            ->assertSessionHas('success');
        $test = QualityTest::firstOrFail();
        $this->assertSame('pending', $test->status);
        $this->assertSame(1, $examiner->notifications()->count());

        // Assigning again hands the same test to another examiner instead of duplicating it.
        $other = $this->examiner();
        $this->actingAs($admin)->post(route('admin.quality.assign'), ['student_id' => $student->id, 'kind' => 'individual', 'juz' => 30, 'examiner_id' => $other->id]);
        $this->assertSame(1, QualityTest::count());
        $this->assertSame($other->id, $test->fresh()->examiner_id);

        // A teacher outside the committee cannot be chosen.
        $this->actingAs($admin)->post(route('admin.quality.assign'), ['student_id' => $student->id, 'kind' => 'individual', 'juz' => 30, 'examiner_id' => $teacher->id])
            ->assertSessionHasErrors('examiner_id');

        // Only the assigned examiner records the result.
        $this->actingAs($examiner)->get(route('teacher.quality.grade', $test))->assertForbidden();
        $this->actingAs($other)->get(route('teacher.quality.index'))->assertOk()->assertSee($student->full_name);
        $parent = $this->parentUser();
        $student->parents()->attach($parent->id);

        $criteria = QualityCriterion::active()->get();
        $low = $criteria->mapWithKeys(fn ($c) => [$c->id => (int) floor($c->max_score / 2)])->all();
        $this->actingAs($other)->post(route('teacher.quality.grade.store', $test), ['scores' => $low, 'tested_on' => now()->toDateString()])->assertRedirect();
        $this->assertSame('failed', $test->fresh()->status);
        $this->assertNotEmpty($service->forStudent($student)['missing_individual']);

        $full = $criteria->mapWithKeys(fn ($c) => [$c->id => $c->max_score])->all();
        $this->actingAs($other)->post(route('teacher.quality.grade.store', $test), ['scores' => $full, 'tested_on' => now()->toDateString(), 'notes' => 'متقن'])->assertRedirect();
        $test->refresh();
        $this->assertSame('passed', $test->status);
        $this->assertEquals(100, $test->percentage);
        $this->assertSame([], $service->forStudent($student->fresh())['missing_individual']);
        $this->assertSame(1, $parent->notifications()->count());

        // The parent's home screen lists the passed test.
        $this->actingAs($parent)->get(route('parent.dashboard'))->assertOk()->assertSee('الاختبارات والدورات المنجزة')->assertSee('الجزء 30 (عمّ)');
    }

    public function test_ajza_known_on_joining_count_for_combined_tests_but_raise_no_alerts(): void
    {
        $student = $this->studentIn($this->circleFor($this->teacher()), ['memorized_juz_at_join' => 3]);
        $service = app(QualityService::class);

        $status = $service->forStudent($student);
        $this->assertSame(27, $status['current_juz']);
        $this->assertSame(3, $status['memorized_count']);
        $this->assertSame([], $status['missing_individual']);
        $this->assertSame(['30-28'], collect($status['due_combined'])->map(fn ($d) => $d['stage']->from_juz.'-'.$d['stage']->to_juz)->all());

        $stage = CombinedTestStage::where('from_juz', 30)->where('to_juz', 28)->first();
        $admin = $this->admin();
        $examiner = $this->examiner();
        $this->actingAs($admin)->post(route('admin.quality.assign'), ['student_id' => $student->id, 'kind' => 'combined', 'stage_id' => $stage->id, 'examiner_id' => $examiner->id])->assertSessionHas('success');
        $test = QualityTest::firstOrFail();
        $this->assertSame([30, 28], [$test->from_juz, $test->to_juz]);

        $full = QualityCriterion::active()->get()->mapWithKeys(fn ($c) => [$c->id => $c->max_score])->all();
        $this->actingAs($admin)->post(route('admin.quality.grade.store', $test), ['scores' => $full, 'tested_on' => now()->toDateString()])->assertRedirect();
        $this->assertSame([], $service->forStudent($student)['due_combined']);

        // Juz 27 finished at the center (now in juz 26) needs its individual test.
        $this->newRecord($student, 46);
        $this->assertSame([27], array_keys($service->forStudent($student)['missing_individual']));
    }

    public function test_admin_manages_committee_criteria_and_ladder(): void
    {
        $admin = $this->admin();
        $teacher = $this->teacher();

        $this->actingAs($admin)->get(route('admin.quality.settings'))->assertOk()->assertSee('مراحل الاختبارات المجتمعة');
        $this->actingAs($admin)->post(route('admin.quality.examiner', $teacher), ['on' => 1])->assertSessionHas('success');
        $this->assertTrue($teacher->fresh()->isQualityExaminer());

        $this->actingAs($admin)->post(route('admin.quality.criteria.store'), ['name' => 'الوقف والابتداء', 'max_score' => 10, 'is_active' => 1])->assertSessionHas('success');
        $this->assertDatabaseHas('quality_criteria', ['name' => 'الوقف والابتداء']);

        $this->actingAs($admin)->put(route('admin.quality.pass-score'), ['quality_pass_score' => 90])->assertSessionHas('success');
        $this->assertSame(90, QualityService::passScore());

        // Typed upside down, saved from the higher juz.
        $this->actingAs($admin)->post(route('admin.quality.stages.store'), ['from_juz' => 11, 'to_juz' => 30, 'name' => 'العشرون', 'sort_order' => 11, 'is_active' => 1])->assertSessionHas('success');
        $this->assertDatabaseHas('combined_test_stages', ['from_juz' => 30, 'to_juz' => 11, 'name' => 'العشرون']);

        // Parents and teachers have no access to the committee pages.
        $this->actingAs($teacher)->get(route('admin.quality.index'))->assertForbidden();
        $this->actingAs($this->parentUser())->get(route('teacher.quality.index'))->assertForbidden();
    }

    public function test_new_student_form_asks_for_memorized_ajza(): void
    {
        $this->actingAs($this->admin())->get(route('admin.students.create'))->assertOk()->assertSee('عدد الأجزاء المحفوظة عند التسجيل');
        $this->actingAs($this->admin())->post(route('admin.students.store'), ['full_name' => 'سعد علي', 'enrollment_date' => now()->toDateString(), 'memorized_juz_at_join' => 31, 'guardian_phone' => '0551231234', 'guardian_name' => 'علي سعد'])
            ->assertSessionHasErrors('memorized_juz_at_join');
    }

    public function test_trial_quality_data_command(): void
    {
        $this->admin();
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher);
        foreach (range(1, 21) as $i) {
            $this->studentIn($circle, ["memorized_juz_at_join" => 3 + $i % 3]);
        }

        $this->artisan('gharas:demo-quality')->assertSuccessful();
        $this->assertGreaterThan(0, QualityTest::count());
        $this->assertTrue($teacher->fresh()->isQualityExaminer());

        // A second run refuses unless asked to rebuild.
        $this->artisan('gharas:demo-quality')->assertFailed();
        $this->artisan('gharas:demo-quality --fresh')->assertSuccessful();
    }
}
