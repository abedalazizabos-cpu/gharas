<?php

namespace Tests\Feature;

use App\Models\RegistrationRequest;
use App\Models\Student;
use Tests\TestCase;

class TeacherStudentRequestTest extends TestCase
{
    private function form(array $over = []): array
    {
        return $over + [
            'student_name' => 'يوسف أحمد السبيعي', 'gender' => 'male', 'birth_date' => now()->subYears(9)->toDateString(), 'memorized_juz' => 1,
            'guardian_name' => 'أحمد السبيعي', 'guardian_phone' => '0591234567', 'relationship' => 'father', 'notes' => 'منتقل من مسجد آخر',
        ];
    }

    public function test_teacher_request_waits_for_the_admin_then_joins_the_circle(): void
    {
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher, ['capacity' => 5]);
        $admin = $this->admin();

        $this->actingAs($teacher)->get(route('teacher.circles.show', $circle))->assertOk()->assertSee('طلب إضافة طالب');
        $this->actingAs($teacher)->get(route('teacher.student-requests.create', $circle))->assertOk()->assertSee('بعد موافقة المدير');
        $this->actingAs($teacher)->post(route('teacher.student-requests.store', $circle), $this->form())->assertSessionHasNoErrors();

        // Nothing is added until the admin accepts.
        $this->assertSame(0, Student::count());
        $req = RegistrationRequest::firstOrFail();
        $this->assertSame([$circle->id, $teacher->id, 'pending'], [$req->circle_id, $req->requested_by, $req->status]);
        $this->assertSame(1, $admin->notifications()->count());
        $this->actingAs($teacher)->get(route('teacher.circles.show', $circle))->assertSee('يوسف أحمد السبيعي')->assertSee('بانتظار الرد');
        $this->actingAs($admin)->get(route('admin.registrations.index'))->assertOk()->assertSee('طلب من المحفظ '.$teacher->name);

        $this->actingAs($admin)->post(route('admin.registrations.accept', $req), ['circle_id' => $circle->id])->assertSessionHasNoErrors();
        $student = Student::firstOrFail();
        $this->assertSame($circle->id, $student->current_circle_id);
        $this->assertSame(1, $student->memorized_juz_at_join);
        $this->assertSame('0591234567', $student->parents->first()->phone);
        $this->assertSame(1, $teacher->notifications()->count());
        $this->actingAs($teacher)->get(route('teacher.circles.show', $circle))->assertSee('مقبول');
    }

    public function test_requests_cannot_exceed_the_circle_capacity(): void
    {
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher, ['capacity' => 2]);
        $this->studentIn($circle);

        // One seat left: the first request takes it, the second is refused.
        $this->actingAs($teacher)->post(route('teacher.student-requests.store', $circle), $this->form())->assertSessionHasNoErrors();
        $this->actingAs($teacher)->post(route('teacher.student-requests.store', $circle), $this->form(['student_name' => 'عمر أحمد السبيعي']))->assertSessionHasErrors('student_name');
        $this->assertSame(1, RegistrationRequest::count());
        $this->actingAs($teacher)->get(route('teacher.student-requests.create', $circle))->assertSee('الحلقة مكتملة');

        // Withdrawing the request frees the seat again.
        $this->actingAs($teacher)->delete(route('teacher.student-requests.destroy', RegistrationRequest::first()))->assertRedirect();
        $this->assertSame(1, $circle->fresh()->freeSeats());
    }

    public function test_teacher_only_requests_for_his_own_circle_and_admin_can_reject(): void
    {
        $teacher = $this->teacher();
        $other = $this->circleFor($this->teacher());
        $this->actingAs($teacher)->get(route('teacher.student-requests.create', $other))->assertForbidden();
        $this->actingAs($teacher)->post(route('teacher.student-requests.store', $other), $this->form())->assertForbidden();

        $circle = $this->circleFor($teacher);
        $this->actingAs($teacher)->post(route('teacher.student-requests.store', $circle), $this->form());
        $req = RegistrationRequest::firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.registrations.reject', $req), ['reject_reason' => 'لا توجد مقاعد'])->assertRedirect();
        $this->assertSame('rejected', $req->fresh()->status);
        $this->assertSame(1, $teacher->notifications()->count());
        $this->actingAs($teacher)->get(route('teacher.circles.show', $circle))->assertSee('مرفوض')->assertSee('لا توجد مقاعد');
        // An answered request can't be withdrawn.
        $this->actingAs($teacher)->delete(route('teacher.student-requests.destroy', $req))->assertForbidden();
    }
}
