<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\MemorizationRecord;
use App\Models\OfflineSubmission;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Forms a teacher filled in without internet and the phone sends later (public/js/offline.js). */
class OfflineSyncTest extends TestCase
{
    private function offline(string $id): array
    {
        return ['X-Offline-Id' => $id, 'Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];
    }

    private function recitation(int $studentId, string $day): array
    {
        return ['student_id' => $studentId, 'recorded_on' => $day, 'type' => 'new', 'surah_id' => 78, 'from_ayah' => 1, 'to_ayah' => 16, 'score' => 92,
            '_offline_user' => '1', '_offline_label' => 'تسميع'];
    }

    public function test_a_form_sent_twice_from_the_phone_is_saved_once_with_its_own_date(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentIn($this->circleFor($teacher));
        $id = (string) Str::uuid();
        $yesterday = now()->subDay()->toDateString();

        $this->actingAs($teacher)->withHeaders($this->offline($id))
            ->post(route('teacher.memorization.store'), $this->recitation($student->id, $yesterday))
            ->assertOk()->assertJson(['status' => 'saved', 'message' => 'تم حفظ سجل التسميع بنجاح.']);

        // The answer was lost on the way, so the phone sends it again.
        $this->actingAs($teacher)->withHeaders($this->offline($id))
            ->post(route('teacher.memorization.store'), $this->recitation($student->id, $yesterday))
            ->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, MemorizationRecord::count());
        $this->assertSame($yesterday, MemorizationRecord::first()->recorded_on->toDateString());
        $this->assertSame(1, OfflineSubmission::count());
        // The "saved" message is not shown again on the next page.
        $this->assertNull(session('success'));
    }

    public function test_a_refused_form_gets_the_reason_and_can_be_sent_again_after_fixing(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentIn($this->circleFor($teacher));
        $id = (string) Str::uuid();

        $this->actingAs($teacher)->withHeaders($this->offline($id))
            ->post(route('teacher.memorization.store'), ['score' => 150] + $this->recitation($student->id, now()->toDateString()))
            ->assertStatus(422)->assertJsonValidationErrors('score');
        $this->assertSame(0, OfflineSubmission::count());

        $this->actingAs($teacher)->withHeaders($this->offline($id))
            ->post(route('teacher.memorization.store'), $this->recitation($student->id, now()->toDateString()))
            ->assertOk()->assertJson(['status' => 'saved']);
        $this->assertSame(1, MemorizationRecord::count());
    }

    public function test_attendance_and_day_off_go_through_the_normal_rules(): void
    {
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher);
        $student = $this->studentIn($circle);
        $day = now()->toDateString();

        $this->actingAs($teacher)->withHeaders($this->offline((string) Str::uuid()))
            ->post(route('teacher.attendance.store', $circle), ['date' => $day, 'attendance' => [$student->id => ['status' => 'absent']]])
            ->assertOk()->assertJson(['status' => 'saved']);
        $this->assertSame('absent', AttendanceRecord::firstOrFail()->status);

        // Another teacher's circle: refused, with the reason, and nothing kept as received.
        $other = $this->circleFor($this->teacher());
        $this->actingAs($teacher)->withHeaders($this->offline((string) Str::uuid()))
            ->post(route('teacher.day-offs.store', $other), ['date' => $day, 'reason' => 'مرض المحفظ'])
            ->assertForbidden();
        $this->assertSame(1, OfflineSubmission::count());
    }

    public function test_session_ended_or_bad_id_is_refused_without_saving(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentIn($this->circleFor($teacher));
        $data = $this->recitation($student->id, now()->toDateString());

        $this->withHeaders($this->offline((string) Str::uuid()))->post(route('teacher.memorization.store'), $data)->assertUnauthorized();
        $this->actingAs($teacher)->withHeaders($this->offline('not-a-uuid'))->post(route('teacher.memorization.store'), $data)->assertStatus(400);

        // The same id from another account is not taken as "already saved" for it.
        $id = (string) Str::uuid();
        $this->actingAs($teacher)->withHeaders($this->offline($id))->post(route('teacher.memorization.store'), $data)->assertOk();
        $other = $this->teacher();
        $this->actingAs($other)->withHeaders($this->offline($id))->post(route('teacher.memorization.store'), $data)->assertStatus(400);
        $this->assertSame(1, MemorizationRecord::count());
    }

    public function test_the_phone_reads_a_fresh_token_and_who_is_signed_in(): void
    {
        $this->getJson(route('offline.token'))->assertOk()->assertJson(['user' => null]);
        $teacher = $this->teacher();
        $this->actingAs($teacher)->getJson(route('offline.token'))->assertOk()->assertJson(['user' => $teacher->id])->assertJsonStructure(['token']);
    }

    public function test_pages_carry_the_user_and_day_and_load_the_offline_script(): void
    {
        $teacher = $this->teacher();
        $this->actingAs($teacher)->get(route('teacher.dashboard'))->assertOk()
            ->assertSee('name="gharas-user" content="'.$teacher->id.'"', false)
            ->assertSee('name="gharas-date" content="'.now()->toDateString().'"', false)
            ->assertSee('js/offline.js', false);
    }
}
