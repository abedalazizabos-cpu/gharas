<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\SmsMessage;
use App\Services\AttendanceService;
use App\Services\SmsService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsTest extends TestCase
{
    private function connect(array $auto = []): void
    {
        Setting::put('sms_enabled', '1');
        Setting::put('sms_username', 'ABCD12');
        Setting::put('sms_password', Crypt::encryptString('secret-pass'));
        Setting::put('sms_country_code', '+970');
        Setting::put('sms_signature', 'مركز غراس');
        foreach ($auto as $k) {
            Setting::put('sms_auto_'.$k, '1');
        }
    }

    public function test_super_admin_connects_the_phone_and_a_test_message_is_sent(): void
    {
        Http::fake([SmsService::DEFAULT_URL => Http::response(['id' => 'abc', 'state' => 'Pending'], 202)]);
        $super = $this->superAdmin();

        $this->actingAs($this->admin())->get(route('admin.sms.settings'))->assertForbidden();
        $this->actingAs($super)->get(route('admin.sms.settings'))->assertOk()->assertSee('SMS Gateway for Android');
        $this->actingAs($super)->put(route('admin.sms.settings.update'), [
            'sms_enabled' => '1', 'sms_username' => 'ABCD12', 'sms_password' => 'secret-pass', 'sms_country_code' => '970',
            'sms_auto_absence' => '1', 'test_phone' => '0599123456',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertNotSame('secret-pass', Setting::get('sms_password'));
        $this->assertSame('+970', Setting::get('sms_country_code'));
        Http::assertSent(fn ($r) => $r->url() === SmsService::DEFAULT_URL
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('ABCD12:secret-pass'))
            && $r['phoneNumbers'] === ['+970599123456'] && str_contains($r['textMessage']['text'], 'رسالة تجربة'));
        $this->assertSame('test', SmsMessage::firstOrFail()->purpose);
    }

    public function test_admin_sends_to_the_parents_of_a_circle(): void
    {
        Http::fake([SmsService::DEFAULT_URL => Http::response(['id' => 'x'], 202)]);
        $this->connect();
        $circle = $this->circleFor($this->teacher());
        $a = $this->studentIn($circle, ['status' => 'active']);
        $b = $this->studentIn($circle, ['status' => 'active']);
        $other = $this->studentIn($this->circleFor($this->teacher()), ['status' => 'active']);
        foreach ([[$a, '0599000001'], [$b, '0599000002'], [$other, '0599000003']] as [$s, $phone]) {
            $p = $this->parentUser();
            $p->forceFill(['phone' => $phone])->save();
            $s->parents()->attach($p->id, ['relationship' => 'أب']);
        }
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.sms.index'))->assertOk()->assertSee($circle->name);
        $this->actingAs($admin)->post(route('admin.sms.send'), ['audience' => 'circle', 'circle_id' => $circle->id, 'text' => 'غداً رحلة للحلقة'])
            ->assertSessionHas('success');

        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => collect($r['phoneNumbers'])->sort()->values()->all() === ['+970599000001', '+970599000002']
            && $r['textMessage']['text'] === "غداً رحلة للحلقة\nمركز غراس");
        $this->assertSame(2, SmsMessage::firstOrFail()->phones_count);
        $this->actingAs($this->teacher())->get(route('admin.sms.index'))->assertForbidden();
    }

    public function test_automatic_absence_message_and_a_down_gateway_does_not_break_saving(): void
    {
        $this->connect(['absence']);
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher);
        $student = $this->studentIn($circle, ['status' => 'active']);
        $parent = $this->parentUser();
        $parent->forceFill(['phone' => '0599111222'])->save();
        $student->parents()->attach($parent->id, ['relationship' => 'أم']);

        Http::fake([SmsService::DEFAULT_URL => Http::sequence()->push(['id' => 'x'], 202)->push(['message' => 'Unauthorized'], 401)]);
        app(AttendanceService::class)->record($circle, now()->toDateString(), [$student->id => ['status' => 'absent']], $teacher);
        Http::assertSent(fn ($r) => $r['phoneNumbers'] === ['+970599111222'] && str_contains($r['textMessage']['text'], 'بغياب '.$student->full_name));

        // Gateway rejects: attendance is still saved and the failure is recorded.
        $other = $this->studentIn($circle, ['status' => 'active']);
        $other->parents()->attach($parent->id, ['relationship' => 'أم']);
        app(AttendanceService::class)->record($circle, now()->toDateString(), [$other->id => ['status' => 'absent']], $teacher);
        $this->assertSame('failed', SmsMessage::latest('id')->first()->status);
        $this->assertSame(2, \App\Models\AttendanceRecord::where('status', 'absent')->count());
    }

    public function test_nothing_is_sent_while_sms_is_off(): void
    {
        Http::fake();
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher);
        $student = $this->studentIn($circle, ['status' => 'active']);
        $student->parents()->attach($this->parentUser()->id, ['relationship' => 'أب']);
        app(AttendanceService::class)->record($circle, now()->toDateString(), [$student->id => ['status' => 'absent']], $teacher);
        Http::assertNothingSent();
        $this->assertSame(1, SmsService::parts(str_repeat('ب', 70)));
        $this->assertSame(2, SmsService::parts(str_repeat('ب', 71)));
    }
}
