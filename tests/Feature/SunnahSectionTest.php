<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\SunnahCourse;
use App\Models\SunnahEnrollment;
use App\Models\SunnahPost;
use App\Models\SunnahPostFollow;
use App\Models\User;
use App\Support\PdfExport;
use Database\Seeders\DemoSeeder;
use Database\Seeders\SunnahDemoSeeder;
use Tests\TestCase;

class SunnahSectionTest extends TestCase
{
    private function coursePayload(User $teacher, array $studentIds, array $extra = []): array
    {
        return $extra + [
            'title' => 'الأربعون النووية', 'category' => 'hadith', 'teacher_id' => $teacher->id, 'status' => 'running',
            'starts_on' => now()->subWeek()->toDateString(), 'ends_on' => now()->addWeeks(5)->toDateString(),
            'schedule' => 'الأحد بعد العصر', 'objectives' => "أن يحفظ الطالب الأحاديث\nأن يفهم معانيها",
            'pass_score' => 70, 'results_to_parents' => 1,
            'criteria' => [['name' => 'حفظ الأحاديث', 'max_score' => 60], ['name' => 'الحضور', 'max_score' => 40]],
            'student_ids' => $studentIds,
        ];
    }

    public function test_admin_creates_a_course_with_students_from_other_circles_and_grading_rules(): void
    {
        $admin = $this->admin();
        $sheikh = $this->teacher();
        $otherTeacher = $this->teacher();
        $parent = $this->parentUser();
        $a = $this->studentIn($this->circleFor($otherTeacher));
        $b = $this->studentIn($this->circleFor($this->teacher()));
        $a->parents()->attach($parent->id, ['relationship' => 'أب']);

        $this->actingAs($admin)->get(route('admin.sunnah.create'))->assertOk()->assertSee('ضوابط التقييم')->assertSee($a->full_name);
        $this->actingAs($admin)->post(route('admin.sunnah.store'), $this->coursePayload($sheikh, [$a->id, $b->id]))->assertSessionHasNoErrors()->assertRedirect();

        $course = SunnahCourse::firstOrFail();
        $this->assertSame($sheikh->id, $course->teacher_id);
        $this->assertSame(['أن يحفظ الطالب الأحاديث', 'أن يفهم معانيها'], $course->objectiveList());
        $this->assertSame(100, $course->load('criteria')->maxTotal());
        $this->assertSame(2, $course->enrollments()->count());
        $this->assertSame(1, $sheikh->notifications()->count());
        $this->assertSame(1, $parent->notifications()->count());

        $this->actingAs($admin)->get(route('admin.sunnah.show', $course))->assertOk()->assertSee('أن يفهم معانيها')->assertSee($b->full_name);
        $this->actingAs($admin)->get(route('admin.sunnah.index'))->assertOk()->assertSee('الأربعون النووية');
    }

    public function test_sheikh_grades_and_parent_sees_the_result(): void
    {
        $admin = $this->admin();
        $sheikh = $this->teacher();
        $parent = $this->parentUser();
        $a = $this->studentIn($this->circleFor($this->teacher()));
        $b = $this->studentIn($this->circleFor($this->teacher()));
        $a->parents()->attach($parent->id, ['relationship' => 'أب']);
        $this->actingAs($admin)->post(route('admin.sunnah.store'), $this->coursePayload($sheikh, [$a->id, $b->id]));
        $course = SunnahCourse::with('criteria')->firstOrFail();
        [$memo, $att] = $course->criteria->all();
        $ea = SunnahEnrollment::where('student_id', $a->id)->firstOrFail();
        $eb = SunnahEnrollment::where('student_id', $b->id)->firstOrFail();

        // Another teacher cannot open the course.
        $this->actingAs($this->teacher())->get(route('teacher.sunnah.grade', $course))->assertForbidden();

        $this->actingAs($sheikh)->get(route('teacher.sunnah.index'))->assertOk()->assertSee('الأربعون النووية');
        $this->actingAs($sheikh)->get(route('teacher.sunnah.grade', $course))->assertOk()->assertSee($a->full_name);
        $this->actingAs($sheikh)->post(route('teacher.sunnah.grade.store', $course), [
            'evaluated_on' => now()->toDateString(),
            'rows' => [
                $ea->id => ['scores' => [$memo->id => 55, $att->id => 36], 'notes' => 'متميز'],
                // Only one score: stays not graded.
                $eb->id => ['scores' => [$memo->id => 30]],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect(route('teacher.sunnah.show', $course));

        $ea->refresh();
        $this->assertSame('passed', $ea->status);
        $this->assertEquals(91, $ea->percentage);
        $this->assertSame('excellent', $ea->grade);
        $this->assertSame('enrolled', $eb->refresh()->status);

        // A score above the rule's maximum is refused.
        $this->actingAs($sheikh)->post(route('teacher.sunnah.grade.store', $course), ['evaluated_on' => now()->toDateString(), 'rows' => [$eb->id => ['scores' => [$memo->id => 80, $att->id => 10]]]])
            ->assertSessionHasErrors();

        $this->assertTrue($parent->notifications()->get()->contains(fn ($n) => $n->data['title'] === 'نتيجة دورة في قسم السنة'));
        $this->actingAs($parent)->get(route('parent.dashboard'))->assertOk()->assertSee('الدورات')->assertSee('الأربعون النووية · ممتاز', false);
        $this->actingAs($parent)->get(route('parent.children.show', $a))->assertOk()->assertSee('أن يفهم معانيها')->assertSee('متميز');

        // Hidden results: the parent sees the course but not the score.
        $course->update(['results_to_parents' => false]);
        $this->actingAs($parent)->get(route('parent.children.show', $a))->assertOk()->assertSee('الأربعون النووية')->assertDontSee('91%');

        // The graded student cannot be removed when the admin edits the participants.
        $this->actingAs($admin)->put(route('admin.sunnah.update', $course), $this->coursePayload($sheikh, [], [
            'criteria' => [['id' => $memo->id, 'name' => 'حفظ الأحاديث', 'max_score' => 60], ['id' => $att->id, 'name' => 'الحضور', 'max_score' => 40]],
        ]))->assertSessionHasNoErrors();
        $this->assertSame([$a->id], $course->enrollments()->pluck('student_id')->all());

        // Removing a rule that already has scores is refused.
        $this->actingAs($admin)->put(route('admin.sunnah.update', $course), $this->coursePayload($sheikh, [$a->id], [
            'criteria' => [['id' => $memo->id, 'name' => 'حفظ الأحاديث', 'max_score' => 60]],
        ]))->assertSessionHasErrors('criteria');
    }

    public function test_sheikh_gives_homework_and_the_parent_follows_it(): void
    {
        $sheikh = $this->teacher();
        $parent = $this->parentUser();
        $other = $this->parentUser();
        $a = $this->studentIn($this->circleFor($this->teacher()));
        $b = $this->studentIn($this->circleFor($this->teacher()));
        $a->parents()->attach($parent->id, ['relationship' => 'أب']);
        $b->parents()->attach($other->id, ['relationship' => 'أب']);
        $this->actingAs($this->admin())->post(route('admin.sunnah.store'), $this->coursePayload($sheikh, [$a->id, $b->id]));
        $course = SunnahCourse::firstOrFail();

        $this->actingAs($sheikh)->post(route('teacher.sunnah.posts.store', $course), [
            'type' => 'homework', 'title' => 'حفظ حديث النية', 'body' => 'مع ثلاث فوائد', 'due_on' => now()->addDays(3)->toDateString(),
        ])->assertSessionHasNoErrors();
        $post = SunnahPost::firstOrFail();
        $this->assertTrue($parent->notifications()->get()->contains(fn ($n) => str_contains($n->data['title'], 'واجب جديد')));

        // Another teacher cannot post in this course.
        $this->actingAs($this->teacher())->post(route('teacher.sunnah.posts.store', $course), ['type' => 'instruction', 'title' => 'x'])->assertForbidden();

        // Before the parent opens the page the sheikh sees nobody followed it.
        $this->actingAs($sheikh)->get(route('teacher.sunnah.show', $course))->assertOk()->assertSee('حفظ حديث النية')->assertSee('اطّلع 0 / 2');

        $this->actingAs($parent)->get(route('parent.dashboard'))->assertOk()->assertSee('الاختبارات المنفردة')->assertSee('الاختبارات المجتمعة')->assertSee(route('parent.children.courses', $a));
        $this->actingAs($parent)->get(route('parent.children.courses', $a))->assertOk()->assertSee('حفظ حديث النية')->assertSee('تأكيد إنجاز الواجب');
        $this->actingAs($parent)->post(route('parent.children.homework', [$a, $post]), ['done' => 1])->assertRedirect();
        $this->assertNotNull(SunnahPostFollow::where('student_id', $a->id)->value('done_at'));

        $this->actingAs($sheikh)->get(route('teacher.sunnah.show', $course))->assertSee('اطّلع 1 / 2')->assertSee('أنجز 1 / 2');

        // A parent cannot follow up another family's child.
        $this->actingAs($other)->get(route('parent.children.courses', $a))->assertForbidden();
        $this->actingAs($other)->post(route('parent.children.homework', [$a, $post]), ['done' => 1])->assertForbidden();

        $this->actingAs($sheikh)->delete(route('teacher.sunnah.posts.destroy', [$course, $post]))->assertRedirect();
        $this->actingAs($parent)->get(route('parent.children.courses', $a))->assertOk()->assertDontSee('تأكيد إنجاز الواجب')->assertDontSee('أنجز ابني الواجب');
    }

    public function test_course_results_download_as_arabic_pdf(): void
    {
        $sheikh = $this->teacher();
        $a = $this->studentIn($this->circleFor($this->teacher()));
        $this->actingAs($this->admin())->post(route('admin.sunnah.store'), $this->coursePayload($sheikh, [$a->id]));
        $course = SunnahCourse::firstOrFail();

        $res = $this->actingAs($sheikh)->get(route('teacher.sunnah.show', [$course, 'export' => 'pdf']));
        $res->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString($a->full_name, PdfExport::lastText());
        $this->assertStringContainsString('أن يحفظ الطالب الأحاديث', PdfExport::lastText());
    }

    public function test_permission_and_visitor_access(): void
    {
        $sheikh = $this->teacher();
        $this->actingAs($this->admin())->post(route('admin.sunnah.store'), $this->coursePayload($sheikh, [$this->studentIn(null)->id]));

        $this->actingAs($sheikh)->get(route('admin.sunnah.index'))->assertForbidden();
        $this->actingAs($this->parentUser())->get(route('teacher.sunnah.index'))->assertForbidden();

        $visitor = User::factory()->visitor()->create();
        $this->actingAs($visitor)->get(route('visitor.sunnah.index'))->assertOk()->assertSee('الأربعون النووية');
    }

    public function test_trial_data_builds_realistic_courses(): void
    {
        $this->seed([DemoSeeder::class, SunnahDemoSeeder::class]);

        $this->assertSame(8, SunnahCourse::count());
        $finished = SunnahCourse::where('status', 'finished')->get();
        $this->assertNotEmpty($finished);
        foreach ($finished as $course) {
            $this->assertSame(0, $course->enrollments()->where('status', 'enrolled')->count());
        }
        $this->assertSame(0, SunnahEnrollment::whereHas('course', fn ($q) => $q->where('status', '!=', 'finished'))->graded()->count());

        // Girls' courses only have girls.
        $girls = SunnahCourse::where('title', 'like', '%الطهارة%')->firstOrFail();
        $this->assertSame(['female'], $girls->enrollments()->with('student')->get()->pluck('student.gender')->unique()->values()->all());

        $parent = User::where('phone', '0550000001')->firstOrFail();
        $this->actingAs($parent)->get(route('parent.dashboard'))->assertOk()->assertSee('الاختبارات المجتمعة')->assertSee('الدورات');
        $this->assertTrue($parent->children->every(fn (Student $c) => $c->sunnahEnrollments()->count() >= 2));
        $this->assertGreaterThan(10, SunnahPost::count());
        $child = $parent->children->first();
        $this->actingAs($parent)->get(route('parent.children.courses', $child))->assertOk()->assertSee('تعليمات الشيخ وواجباته')->assertSee('أهداف الدورة');

        $teacher = SunnahCourse::firstOrFail()->teacher;
        $this->actingAs($teacher)->get(route('teacher.dashboard'))->assertOk();
        $this->actingAs(User::admins()->first())->get(route('admin.sunnah.show', SunnahCourse::first()))->assertOk();
    }
}
