<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\CircleSession;
use App\Models\HonorEntry;
use App\Models\MemorizationRecord;
use App\Models\PushSubscription;
use App\Models\RegistrationRequest;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\HonorBoardService;
use App\Services\WebPushService;
use App\Support\Hijri;
use App\Support\PdfExport;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CenterExtrasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function day(Student $s, string $date, int $score, float $pages, string $status = 'present'): void
    {
        $circle = $s->circle;
        $session = CircleSession::firstOrCreate(['circle_id' => $circle->id, 'session_date' => $date], ['teacher_id' => $circle->teacher_id]);
        AttendanceRecord::create(['circle_session_id' => $session->id, 'student_id' => $s->id, 'status' => $status, 'recorded_by' => $circle->teacher_id]);
        if ($status !== 'absent') {
            MemorizationRecord::create(['student_id' => $s->id, 'circle_id' => $circle->id, 'teacher_id' => $circle->teacher_id, 'recorded_on' => $date,
                'type' => 'new', 'surah_id' => 67, 'from_ayah' => 1, 'to_ayah' => 5, 'pages' => $pages, 'score' => $score, 'shared_with_parent' => true, 'notes' => 'أحسنت']);
        }
    }

    public function test_honor_board_picks_one_student_per_circle_for_last_month_and_shows_it_on_the_login_screen(): void
    {
        $a = $this->circleFor($this->teacher(), ['name' => 'حلقة الفاروق']);
        $b = $this->circleFor($this->teacher(), ['name' => 'حلقة الصديق']);
        $best = $this->studentIn($a, ['full_name' => 'يوسف أحمد محمود السبيعي', 'status' => 'active']);
        $other = $this->studentIn($a, ['full_name' => 'خالد سعيد علي', 'status' => 'active']);
        $onlyB = $this->studentIn($b, ['full_name' => 'عمر حسن صالح', 'status' => 'active']);
        $this->studentIn($b, ['full_name' => 'طالب بلا تسميع', 'status' => 'active']);
        foreach (['2026-10-03', '2026-10-04', '2026-10-05'] as $d) {
            $this->day($best, $d, 95, 1);
            $this->day($other, $d, 80, 0.5);
            $this->day($onlyB, $d, 70, 0.25);
        }
        $this->day($other, '2026-10-06', 0, 0, 'absent');
        // This month does not count yet.
        $this->day($other, '2026-11-08', 100, 5);
        $parent = $this->parentUser();
        $best->parents()->attach($parent->id, ['relationship' => 'father']);

        $this->get(route('login'))->assertOk()->assertSee('لوحة الشرف')->assertSee('أكتوبر 2026')
            ->assertSee('يوسف أحمد')->assertDontSee('يوسف أحمد محمود')->assertSee('عمر حسن')->assertDontSee('خالد سعيد')->assertSee('حلقة الفاروق');
        $this->assertSame(2, HonorEntry::where('month', '2026-10')->count());
        $this->assertSame(1, $parent->notifications()->count());
        $this->assertStringContainsString('لوحة شرف', $parent->notifications()->first()->data['body']);

        // Opening the page again chooses nothing new.
        $this->get(route('login'))->assertOk();
        $this->assertSame(1, $parent->notifications()->count());

        // The admin puts another student instead; a new automatic run keeps the hand-picked one.
        $admin = $this->admin();
        $entry = HonorEntry::where('circle_id', $a->id)->first();
        $this->actingAs($admin)->get(route('admin.honor.index'))->assertOk()->assertSee('يوسف أحمد محمود السبيعي');
        $this->actingAs($admin)->post(route('admin.honor.replace', $entry), ['student_id' => $other->id])->assertSessionHas('success');
        app(HonorBoardService::class)->choose('2026-10');
        $this->assertSame($other->id, $entry->fresh()->student_id);
        $this->actingAs($admin)->post(route('admin.honor.replace', $entry), ['student_id' => $onlyB->id])->assertStatus(422);

        Setting::put('honor_names', 'full');
        auth()->logout();
        $this->get(route('login'))->assertSee('خالد سعيد علي');
        Setting::put('honor_on_login', '0');
        $this->get(route('login'))->assertDontSee('لوحة الشرف');
        $this->actingAs($this->teacher())->get(route('admin.honor.index'))->assertForbidden();
    }

    public function test_monthly_report_pdf_for_parent_teacher_and_admin_and_parents_are_told_once(): void
    {
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher, ['name' => 'حلقة النور']);
        $student = $this->studentIn($circle, ['full_name' => 'سلمان فهد', 'status' => 'active', 'enrollment_date' => '2026-09-01']);
        $this->day($student, '2026-10-10', 92, 2);
        $this->day($student, '2026-10-11', 0, 0, 'absent');
        $parent = $this->parentUser();
        $student->parents()->attach($parent->id, ['relationship' => 'father']);

        $this->actingAs($parent)->get(route('parent.children.show', $student))->assertOk()->assertSee('التقرير الشهري')->assertSee('أكتوبر 2026');
        $this->actingAs($parent)->get(route('monthly-reports.download', [$student, 'month' => '2026-10']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $text = PdfExport::lastText();
        $this->assertStringContainsString('سلمان فهد', $text);
        $this->assertStringContainsString('صفحتان', $text);
        $this->assertStringContainsString('غائب', $text);
        $this->assertStringContainsString('50%', $text);
        $this->assertStringContainsString('هـ', PdfExport::$last['subtitle']);

        $this->actingAs($teacher)->get(route('monthly-reports.download', [$student, 'month' => '2026-10']))->assertOk();
        $this->actingAs($this->admin())->get(route('monthly-reports.download', [$student, 'month' => '2026-10']))->assertOk();
        $this->actingAs($this->parentUser())->get(route('monthly-reports.download', $student))->assertForbidden();
        $this->actingAs($this->teacher())->get(route('monthly-reports.download', $student))->assertForbidden();

        $this->artisan('gharas:monthly-reports')->assertSuccessful();
        $this->artisan('gharas:monthly-reports')->assertSuccessful();
        $this->assertSame(1, $parent->notifications()->count());
        $this->assertSame('التقرير الشهري', $parent->notifications()->first()->data['title']);
        $this->assertStringContainsString('month=2026-10', $parent->notifications()->first()->data['url']);
    }

    public function test_parent_registers_a_child_from_the_website_and_the_admin_accepts_it(): void
    {
        $admin = $this->admin();
        $this->get(route('home'))->assertOk()->assertSee(route('registration.create'));
        $this->get(route('login'))->assertOk()->assertSee('تسجيل طالب جديد');
        $form = ['student_name' => 'أنس محمد العلي', 'gender' => 'male', 'birth_date' => '2016-05-01', 'memorized_juz' => 2,
            'guardian_name' => 'محمد العلي', 'guardian_phone' => '0599111222', 'relationship' => 'father', 'notes' => 'بعد العصر أفضل'];
        $this->post(route('registration.store'), $form)->assertRedirect(route('registration.create'));
        $this->post(route('registration.store'), $form); // sent twice by mistake
        $this->assertSame(1, RegistrationRequest::count());
        $this->post(route('registration.store'), ['guardian_phone' => '123'] + $form)->assertSessionHasErrors('guardian_phone');
        $this->post(route('registration.store'), ['website' => 'spam'] + $form);
        $this->assertSame(1, RegistrationRequest::count());

        $this->assertSame(1, $admin->notifications()->count());
        $circle = $this->circleFor($this->teacher(), ['name' => 'حلقة الإتقان']);
        $req = RegistrationRequest::firstOrFail();
        $this->actingAs($admin)->get(route('admin.registrations.index'))->assertOk()->assertSee('أنس محمد العلي')->assertSee('بعد العصر أفضل');
        $this->actingAs($admin)->post(route('admin.registrations.accept', $req), ['circle_id' => $circle->id])->assertSessionHas('guardian_notice');

        $student = Student::where('full_name', 'أنس محمد العلي')->firstOrFail();
        $this->assertSame($circle->id, $student->current_circle_id);
        $this->assertSame(2, $student->memorized_juz_at_join);
        $guardian = User::where('phone', '0599111222')->firstOrFail();
        $this->assertSame(User::ROLE_PARENT, $guardian->role);
        $this->assertTrue($guardian->isParentOf($student));
        $this->assertSame('accepted', $req->fresh()->status);

        // A brother sent later is added to the same parent account.
        $this->post(route('registration.store'), ['student_name' => 'بلال محمد العلي'] + $form);
        $this->actingAs($admin)->post(route('admin.registrations.accept', RegistrationRequest::where('status', 'pending')->first()));
        $this->assertSame(2, $guardian->children()->count());
        $this->assertSame(1, User::where('phone', '0599111222')->count());

        $this->post(route('registration.store'), ['student_name' => 'طالب يُرفض الطلب'] + $form);
        $this->actingAs($admin)->post(route('admin.registrations.reject', RegistrationRequest::where('status', 'pending')->first()), ['reject_reason' => 'لا مكان'])->assertSessionHas('success');
        $this->assertSame('rejected', RegistrationRequest::latest('id')->first()->status);
        $this->actingAs($this->teacher())->get(route('admin.registrations.index'))->assertForbidden();
    }

    public function test_hijri_dates_beside_the_gregorian_ones(): void
    {
        $this->assertSame('2 جمادى الآخرة 1448هـ', Hijri::format('2026-11-12'));
        $this->assertSame('1 رمضان 1447هـ', Hijri::format('2026-02-18'));
        $this->assertSame('2026/11/12 - 2 جمادى الآخرة 1448هـ', Hijri::both('2026-11-12'));
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()->assertSee('جمادى الآخرة 1448هـ');

        Setting::put('hijri_dates', '0');
        $this->assertSame('2026/11/12', Hijri::both('2026-11-12'));
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()->assertDontSee('1448هـ');
    }

    public function test_the_site_installs_as_an_app_and_notifications_reach_the_phone(): void
    {
        $fake = new class extends WebPushService
        {
            public function keys(): ?array
            {
                return ['publicKey' => 'BPublicKeyForTests', 'privateKey' => 'private'];
            }

            public function flush(): int
            {
                $n = count($this->queue);
                $this->queue = [];

                return $n;
            }
        };
        $this->app->instance(WebPushService::class, $fake);

        $this->get(route('pwa.manifest'))->assertOk()->assertJsonPath('display', 'standalone')->assertJsonPath('dir', 'rtl')->assertJsonCount(3, 'icons');
        $this->get(route('pwa.icon', 192))->assertOk();
        $this->assertFileExists(public_path('sw.js'));
        $this->get(route('login'))->assertSee('manifest.webmanifest', false);

        $parent = $this->parentUser();
        $this->actingAs($parent)->get(route('parent.dashboard'))->assertOk()->assertSee('data-vapid="BPublicKeyForTests"', false)->assertSee('تفعيل الإشعارات على هذا الجهاز');
        $this->actingAs($parent)->postJson(route('push.subscribe'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'key', 'auth' => 'auth']])->assertOk();
        $this->actingAs($parent)->postJson(route('push.subscribe'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'key2', 'auth' => 'auth']])->assertOk();
        $this->assertSame(1, PushSubscription::count());
        $this->actingAs($parent)->postJson(route('push.subscribe'), ['endpoint' => 'http://insecure.test/x', 'keys' => ['p256dh' => 'k', 'auth' => 'a']])->assertUnprocessable();

        $parent->notify(new SystemNotification('شهادة جديدة', 'صدرت شهادة', 'https://example.test/x'));
        $this->assertCount(1, $fake->pending());
        $this->assertStringContainsString('شهادة جديدة', $fake->pending()[0]['payload']);
        $this->assertSame(1, $parent->notifications()->count());
        // Users without a phone subscription get the in-site notification only.
        $this->teacher()->notify(new SystemNotification('x', 'y'));
        $this->assertCount(1, $fake->pending());

        $this->actingAs($parent)->postJson(route('push.unsubscribe'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc'])->assertOk();
        $this->assertSame(0, PushSubscription::count());
    }
}
