<?php

namespace Tests\Feature;

use App\Models\MemorizationRecord;
use App\Services\ParentFollowService;
use Tests\TestCase;

class ParentFollowUpTest extends TestCase
{
    private function record($student, $circle, $teacher, array $extra = []): MemorizationRecord
    {
        return MemorizationRecord::create($extra + [
            'student_id' => $student->id, 'circle_id' => $circle->id, 'teacher_id' => $teacher->id,
            'recorded_on' => now(), 'type' => 'new', 'surah_id' => 112, 'from_ayah' => 1, 'to_ayah' => 2,
            'score' => 90, 'shared_with_parent' => true,
        ]);
    }

    public function test_opening_the_child_page_marks_shared_records_seen_for_the_teacher(): void
    {
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher);
        $student = $this->studentIn($circle);
        $parent = $this->parentUser();
        $parent->children()->attach($student->id);
        $shared = $this->record($student, $circle, $teacher);
        $internal = $this->record($student, $circle, $teacher, ['shared_with_parent' => false]);

        $this->actingAs($teacher)->get(route('teacher.students.show', $student))
            ->assertOk()->assertSee('لم يطّلع')->assertDontSee('bi-check2-all');

        $this->actingAs($parent)->get(route('parent.children.show', $student))->assertOk();

        $this->assertDatabaseHas('memorization_record_views', ['memorization_record_id' => $shared->id, 'parent_id' => $parent->id]);
        $this->assertDatabaseMissing('memorization_record_views', ['memorization_record_id' => $internal->id]);
        $this->actingAs($teacher)->get(route('teacher.students.show', $student))
            ->assertOk()->assertSee('bi-check2-all')->assertSee($parent->name)->assertDontSee('لم يطّلع');

        // Opening the page again does not duplicate the view.
        $this->actingAs($parent)->get(route('parent.children.show', $student))->assertOk();
        $this->assertSame(1, \DB::table('memorization_record_views')->count());
    }

    public function test_follow_up_rate_per_parent_and_child(): void
    {
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher);
        [$ali, $omar] = [$this->studentIn($circle), $this->studentIn($circle)];
        $father = $this->parentUser();
        $father->children()->attach([$ali->id, $omar->id]);
        foreach (range(1, 2) as $i) {
            $this->record($ali, $circle, $teacher);
            $this->record($omar, $circle, $teacher);
        }

        $this->actingAs($father)->get(route('parent.children.show', $ali))->assertOk();
        $this->record($ali, $circle, $teacher); // shared after his last visit

        $follow = app(ParentFollowService::class)->forParent($father);
        $this->assertSame(['seen' => 2, 'total' => 5, 'percent' => 40], $follow['overall']);
        $this->assertSame(67, $follow['children'][$ali->id]['percent']);
        $this->assertSame(0, $follow['children'][$omar->id]['percent']);

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.parents.show', $father))->assertOk()->assertSee('نسبة متابعة الأبناء')->assertSee('40%');
        $this->actingAs($admin)->get(route('admin.parents.index'))->assertOk()->assertSee('40%');
        $this->actingAs($father)->get(route('parent.dashboard'))->assertOk()->assertSee('نسبة متابعتك لأبنائك')->assertSee('40%');
    }

    public function test_a_parent_with_nothing_shared_has_no_rate(): void
    {
        $parent = $this->parentUser();
        $parent->children()->attach($this->studentIn(null)->id);

        $this->assertNull(app(ParentFollowService::class)->forParent($parent)['overall']['percent']);
        $this->actingAs($parent)->get(route('parent.dashboard'))->assertOk()->assertDontSee('نسبة متابعتك لأبنائك');
    }
}
