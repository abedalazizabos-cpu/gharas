<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\QualityTest;
use App\Models\SunnahAttendance;
use App\Models\SunnahCourse;
use App\Models\SunnahEnrollment;
use App\Models\SunnahSession;
use App\Models\User;
use App\Support\PdfExport;
use Database\Seeders\CertificateDemoSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\SunnahDemoSeeder;
use Tests\TestCase;

class CertificatesTest extends TestCase
{
    /** A finished course with one passed student whose parent is $parent. */
    private function passedCourse(User $sheikh, $student, array $course = []): SunnahEnrollment
    {
        $c = SunnahCourse::create($course + ['title' => 'آداب حملة القرآن', 'category' => 'quran_etiquette', 'teacher_id' => $sheikh->id, 'status' => 'finished', 'pass_score' => 70]);

        return SunnahEnrollment::create(['sunnah_course_id' => $c->id, 'student_id' => $student->id, 'status' => 'passed', 'percentage' => 92, 'grade' => 'excellent', 'evaluated_on' => now()->subDays(3)->toDateString()]);
    }

    public function test_nothing_is_issued_until_the_admin_issues_it_then_parent_and_teachers_download_it(): void
    {
        $admin = $this->admin();
        $circleTeacher = $this->teacher();
        $sheikh = $this->teacher();
        $parent = $this->parentUser();
        $student = $this->studentIn($this->circleFor($circleTeacher), ['gender' => 'female']);
        $student->parents()->attach($parent->id, ['relationship' => 'أب']);
        $enrollment = $this->passedCourse($sheikh, $student);

        // Passing alone issues nothing.
        $this->assertSame(0, Certificate::count());
        $this->actingAs($admin)->get(route('admin.certificates.index'))->assertOk()->assertSee($student->full_name)->assertSee('دورة «آداب حملة القرآن»');

        $this->actingAs($admin)->post(route('admin.certificates.issue'), ['items' => ['course:'.$enrollment->id], 'issued_on' => now()->toDateString()])
            ->assertSessionHasNoErrors()->assertRedirect();
        $certificate = Certificate::firstOrFail();
        $this->assertSame('GH-'.now()->year.'-0001', $certificate->number);
        $this->assertSame('course', $certificate->kind);
        $this->assertStringContainsString('أتمّت دورة «آداب حملة القرآن»', $certificate->content['body']);
        $this->assertStringContainsString($sheikh->name, $certificate->content['body']);
        $this->assertSame(1, $parent->notifications()->count());

        // Issuing twice is ignored.
        $this->actingAs($admin)->post(route('admin.certificates.issue'), ['items' => ['course:'.$enrollment->id], 'issued_on' => now()->toDateString()]);
        $this->assertSame(1, Certificate::count());

        // The parent sees it on the child's page and on the courses page, and downloads the PDF.
        $this->actingAs($parent)->get(route('parent.children.show', $student))->assertOk()->assertSee($certificate->number)->assertSee(route('certificates.download', $certificate));
        $this->actingAs($parent)->get(route('parent.children.courses', $student))->assertOk()->assertSee('شهادة الدورة PDF');
        $this->actingAs($parent)->get(route('parent.dashboard'))->assertOk()->assertSee('شهادة صادرة');
        $this->actingAs($parent)->get(route('certificates.download', $certificate))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame($student->full_name, PdfExport::$last['subtitle']);
        $this->assertStringContainsString('شهادة إتمام دورة', PdfExport::$last['title']);

        // The circle teacher and the course sheikh see and download it.
        $this->actingAs($circleTeacher)->get(route('teacher.certificates.index'))->assertOk()->assertSee($student->full_name);
        $this->actingAs($circleTeacher)->get(route('teacher.students.show', $student))->assertOk()->assertSee($certificate->number);
        $this->actingAs($circleTeacher)->get(route('certificates.download', $certificate))->assertOk();
        $this->actingAs($sheikh)->get(route('teacher.certificates.index'))->assertOk()->assertSee($student->full_name);
        $this->actingAs($sheikh)->get(route('certificates.download', $certificate))->assertOk();

        // Nobody else.
        $this->actingAs($this->parentUser())->get(route('certificates.download', $certificate))->assertForbidden();
        $this->actingAs($this->teacher())->get(route('certificates.download', $certificate))->assertForbidden();
        $this->actingAs($this->teacher())->get(route('teacher.certificates.index'))->assertOk()->assertDontSee($student->full_name);
        $this->actingAs($sheikh)->get(route('admin.certificates.index'))->assertForbidden();
    }

    public function test_admin_writes_the_wording_and_issued_certificates_keep_theirs(): void
    {
        $admin = $this->admin();
        $examiner = $this->teacher();
        $student = $this->studentIn($this->circleFor($this->teacher()));
        $test = QualityTest::create(['student_id' => $student->id, 'kind' => 'individual', 'juz' => 30, 'from_juz' => 30, 'to_juz' => 30, 'assigned_on' => now()->toDateString(), 'examiner_id' => $examiner->id, 'status' => 'passed', 'tested_on' => now()->toDateString(), 'percentage' => 96.5, 'grade' => 'excellent']);

        $this->actingAs($admin)->post(route('admin.certificates.issue'), ['items' => ['test:'.$test->id], 'issued_on' => now()->toDateString()]);
        $first = Certificate::firstOrFail();
        $this->assertSame('individual', $first->kind);
        $this->assertStringContainsString('96.5%', $first->content['body']);
        $this->assertSame($examiner->name, $first->content['signer2_name']);

        $template = CertificateTemplate::for('individual');
        $this->actingAs($admin)->put(route('admin.certificates.templates.update', $template), [
            'title' => 'شهادة تفوق', 'intro' => 'يسر {المركز} أن يمنح {اللقب}', 'body' => 'شهادة اجتياز {الجزء} بتقدير {التقدير}.',
            'closing' => null, 'signer1_title' => 'المدير', 'signer1_name' => 'أبو عبدالله', 'signer2_title' => null, 'signer2_name' => null,
            'footer' => 'رقم {رقم_الشهادة}', 'color' => '#7a3e9d',
        ])->assertSessionHasNoErrors();
        $this->assertSame('شهادة تفوق', $template->refresh()->title);
        $this->assertSame(1, \App\Models\AuditLog::where('action', 'certificate.template')->count());

        // The issued one keeps its wording; a new one uses the new wording.
        $this->assertSame('شهادة اجتياز اختبار', $first->refresh()->content['title']);
        $this->actingAs($admin)->get(route('admin.certificates.templates.preview', $template))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('شهادة تفوق', PdfExport::$last['title']);

        // Withdrawing returns the result to the eligible list; issuing again uses the new wording.
        $this->actingAs($admin)->delete(route('admin.certificates.destroy', $first))->assertRedirect();
        $this->assertSame(0, Certificate::count());
        $this->actingAs($admin)->post(route('admin.certificates.issue'), ['items' => ['test:'.$test->id], 'issued_on' => now()->toDateString()]);
        $again = Certificate::firstOrFail();
        $this->assertSame('شهادة تفوق', $again->content['title']);
        $this->assertSame('رقم '.$again->number, $again->content['footer']);
        $this->assertNotSame($first->number, $again->number);
    }

    public function test_the_attendance_rule_is_filled_from_real_attendance(): void
    {
        $sheikh = $this->teacher();
        $a = $this->studentIn($this->circleFor($this->teacher()));
        $course = SunnahCourse::create(['title' => 'السيرة', 'category' => 'seerah', 'teacher_id' => $sheikh->id, 'status' => 'running', 'pass_score' => 70]);
        $memo = $course->criteria()->create(['name' => 'الاختبار', 'max_score' => 80, 'sort_order' => 1]);
        $att = $course->criteria()->create(['name' => 'الحضور', 'max_score' => 20, 'from_attendance' => true, 'sort_order' => 2]);
        $e = SunnahEnrollment::create(['sunnah_course_id' => $course->id, 'student_id' => $a->id]);
        foreach (['present', 'present', 'late', 'absent'] as $i => $status) {
            $session = SunnahSession::create(['sunnah_course_id' => $course->id, 'held_on' => now()->subDays(10 - $i)->toDateString(), 'taken_by' => $sheikh->id]);
            SunnahAttendance::create(['sunnah_session_id' => $session->id, 'student_id' => $a->id, 'status' => $status]);
        }

        $this->actingAs($sheikh)->get(route('teacher.sunnah.grade', $course))->assertOk()->assertSee('حضور 75%');
        // A typed attendance score is ignored: 75% of 20 = 15.
        $this->actingAs($sheikh)->post(route('teacher.sunnah.grade.store', $course), [
            'evaluated_on' => now()->toDateString(), 'rows' => [$e->id => ['scores' => [$memo->id => 70, $att->id => 20]]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(15, $e->scores()->where('sunnah_criterion_id', $att->id)->value('score'));
        $this->assertEquals(85, $e->refresh()->percentage);
    }

    public function test_course_status_follows_the_dates_and_only_moves_forward(): void
    {
        $t = $this->teacher();
        $starts = SunnahCourse::create(['title' => 'أ', 'category' => 'other', 'teacher_id' => $t->id, 'status' => 'planned', 'starts_on' => now()->toDateString(), 'ends_on' => now()->addWeek()->toDateString()]);
        $ended = SunnahCourse::create(['title' => 'ب', 'category' => 'other', 'teacher_id' => $t->id, 'status' => 'running', 'starts_on' => now()->subMonth()->toDateString(), 'ends_on' => now()->subDay()->toDateString()]);
        $later = SunnahCourse::create(['title' => 'ج', 'category' => 'other', 'teacher_id' => $t->id, 'status' => 'planned', 'starts_on' => now()->addWeek()->toDateString()]);
        $early = SunnahCourse::create(['title' => 'د', 'category' => 'other', 'teacher_id' => $t->id, 'status' => 'running', 'starts_on' => now()->addWeek()->toDateString()]);

        $this->actingAs($this->admin())->get(route('admin.sunnah.index'))->assertOk();

        $this->assertSame('running', $starts->refresh()->status);
        $this->assertSame('finished', $ended->refresh()->status);
        $this->assertSame('planned', $later->refresh()->status);
        $this->assertSame('running', $early->refresh()->status);
        $this->artisan('gharas:sunnah-status')->assertSuccessful();
    }

    public function test_parent_and_course_sheikh_can_message_each_other(): void
    {
        $sheikh = $this->teacher();
        $parent = $this->parentUser();
        $student = $this->studentIn($this->circleFor($this->teacher()));
        $student->parents()->attach($parent->id, ['relationship' => 'أب']);
        $this->passedCourse($sheikh, $student, ['status' => 'running']);

        $this->actingAs($parent)->get(route('parent.children.courses', $student))->assertOk()->assertSee('مراسلة شيخ الدورة');
        $this->actingAs($parent)->post(route('messages.store'), ['recipient_id' => $sheikh->id, 'student_id' => $student->id, 'subject' => 'سؤال عن الدورة', 'body' => 'السلام عليكم'])
            ->assertSessionHasNoErrors();
        $this->actingAs($sheikh)->post(route('messages.store'), ['recipient_id' => $parent->id, 'student_id' => $student->id, 'subject' => 'رد', 'body' => 'وعليكم السلام'])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, \App\Models\Message::count());

        // A teacher with no link to the child stays unreachable.
        $this->actingAs($parent)->post(route('messages.store'), ['recipient_id' => $this->teacher()->id, 'subject' => 'x', 'body' => 'y'])->assertSessionHasErrors('recipient_id');
    }

    public function test_courses_appear_in_the_center_report_and_visitor_pdf(): void
    {
        $sheikh = $this->teacher();
        $this->passedCourse($sheikh, $this->studentIn($this->circleFor($this->teacher())), ['starts_on' => now()->subWeek()->toDateString(), 'ends_on' => now()->subDay()->toDateString()]);

        $this->actingAs($this->admin())->get(route('admin.reports.center'))->assertOk()->assertSee('دورات قسم السنة في الفترة')->assertSee('آداب حملة القرآن');
        $this->actingAs($this->admin())->get(route('admin.reports.center', ['export' => 'pdf']))->assertOk();
        $this->assertStringContainsString('آداب حملة القرآن', PdfExport::lastText());

        $visitor = User::factory()->visitor()->create();
        $this->actingAs($visitor)->get(route('visitor.reports.center'))->assertOk()->assertSee('آداب حملة القرآن');
        $this->actingAs($visitor)->get(route('visitor.sunnah.index', ['export' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('آداب حملة القرآن', PdfExport::lastText());
    }

    public function test_trial_data_has_certificates_for_the_demo_parent(): void
    {
        $this->seed([DemoSeeder::class, SunnahDemoSeeder::class, CertificateDemoSeeder::class]);

        $this->assertGreaterThan(50, Certificate::count());
        $this->assertNotEmpty(app(\App\Services\CertificateService::class)->eligible());
        $parent = User::where('phone', '0550000001')->firstOrFail();
        $mine = Certificate::whereIn('student_id', $parent->children()->pluck('students.id'))->get();
        $this->assertContains('course', $mine->pluck('kind')->all());
        $this->assertTrue($mine->every(fn ($c) => app(\App\Services\CertificateService::class)->canView($parent, $c)));
    }
}
