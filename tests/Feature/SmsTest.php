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

    public function test_sms_is_hidden_from_admins_until_the_super_admin_connects_the_phone(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('رسائل SMS للأهالي');
        $this->actingAs($admin)->get(route('admin.sms.index'))->assertNotFound();
        $this->actingAs($admin)->post(route('admin.sms.send'), ['audience' => 'all', 'text' => 'x'])->assertNotFound();

        $super = $this->superAdmin();
        $this->actingAs($super)->get(route('admin.dashboard'))->assertOk()->assertSee('رسائل SMS للأهالي');
        $this->actingAs($super)->get(route('admin.sms.index'))->assertOk();

        Http::fake();
        $this->connect();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('رسائل SMS للأهالي');
        $this->actingAs($admin)->get(route('admin.sms.index'))->assertOk();
    }

    private const PROVIDER = 'https://sms.example.ps/api/send';

    private function connectProvider(array $extra = []): void
    {
        foreach ($extra + [
            'sms_provider_enabled' => '1', 'sms_provider_url' => self::PROVIDER, 'sms_provider_method' => 'POST',
            'sms_provider_to_param' => 'to', 'sms_provider_text_param' => 'message', 'sms_provider_sender_param' => 'sender',
            'sms_provider_sender' => 'Gharas', 'sms_provider_key_param' => 'api_key', 'sms_provider_number_format' => 'intl',
            'sms_country_code' => '+970', 'sms_signature' => 'مركز غراس',
        ] as $k => $v) {
            Setting::put($k, $v);
        }
        Setting::put('sms_provider_key', Crypt::encryptString('key-123'));
    }

    public function test_super_admin_connects_a_paid_provider_and_a_test_message_goes_through_it(): void
    {
        Http::fake([self::PROVIDER.'*' => Http::response('OK: 1 sent', 200)]);
        $super = $this->superAdmin();

        $this->actingAs($super)->put(route('admin.sms.settings.update'), [
            'sms_provider_enabled' => '1', 'sms_provider_url' => self::PROVIDER, 'sms_provider_method' => 'GET',
            'sms_provider_to_param' => 'numbers', 'sms_provider_text_param' => 'text', 'sms_provider_sender_param' => 'from',
            'sms_provider_sender' => 'Gharas', 'sms_provider_key_param' => 'key', 'sms_provider_key' => 'key-123',
            'sms_provider_number_format' => 'local', 'sms_provider_success' => 'OK', 'sms_provider_extra' => "lang=ar\nunicode=1",
            'sms_country_code' => '970', 'test_phone' => '0599123456', 'test_channel' => 'provider',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertNotSame('key-123', Setting::get('sms_provider_key'));
        Http::assertSent(fn ($r) => $r->method() === 'GET' && str_starts_with($r->url(), self::PROVIDER)
            && $r['numbers'] === '0599123456' && $r['from'] === 'Gharas' && $r['key'] === 'key-123'
            && $r['lang'] === 'ar' && $r['unicode'] === '1' && str_contains($r['text'], 'رسالة تجربة'));
        $msg = SmsMessage::firstOrFail();
        $this->assertSame(['provider', 'test'], [$msg->channel, $msg->purpose]);
        $this->assertNotSame('failed', $msg->status);
        // The key is never shown back on the page.
        $this->actingAs($super)->get(route('admin.sms.settings'))->assertOk()->assertDontSee('key-123');
    }

    public function test_admin_chooses_the_channel_and_a_missing_success_word_counts_as_failure(): void
    {
        Http::fake([
            SmsService::DEFAULT_URL => Http::response(['id' => 'x'], 202),
            self::PROVIDER => Http::sequence()->push('OK', 200)->push('ERROR: no balance', 200),
        ]);
        $this->connect();
        $this->connectProvider(['sms_provider_bulk' => '1', 'sms_provider_success' => 'OK']);
        $circle = $this->circleFor($this->teacher());
        foreach (['0599000001', '0599000002'] as $phone) {
            $p = $this->parentUser();
            $p->forceFill(['phone' => $phone])->save();
            $this->studentIn($circle, ['status' => 'active'])->parents()->attach($p->id, ['relationship' => 'أب']);
        }
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.sms.index'))->assertOk()->assertSee('مزوّد الرسائل');

        $this->actingAs($admin)->post(route('admin.sms.send'), ['audience' => 'circle', 'circle_id' => $circle->id, 'text' => 'تنبيه', 'channel' => 'provider'])
            ->assertSessionHas('success');
        Http::assertSent(fn ($r) => $r->url() === self::PROVIDER && collect(explode(',', $r['to']))->sort()->values()->all() === ['970599000001', '970599000002']
            && $r['api_key'] === 'key-123' && $r['sender'] === 'Gharas');
        Http::assertNotSent(fn ($r) => $r->url() === SmsService::DEFAULT_URL);
        $this->assertSame('provider', SmsMessage::latest('id')->first()->channel);

        $this->actingAs($admin)->post(route('admin.sms.send'), ['audience' => 'circle', 'circle_id' => $circle->id, 'text' => 'تنبيه ثان', 'channel' => 'provider']);
        $this->assertSame('failed', SmsMessage::latest('id')->first()->status);

        $this->actingAs($admin)->post(route('admin.sms.send'), ['audience' => 'circle', 'circle_id' => $circle->id, 'text' => 'عبر الجوال', 'channel' => 'phone'])
            ->assertSessionHas('success');
        Http::assertSent(fn ($r) => $r->url() === SmsService::DEFAULT_URL && $r['textMessage']['text'] === "عبر الجوال\nمركز غراس");
        $this->assertSame('phone', SmsMessage::latest('id')->first()->channel);
    }

    public function test_automatic_messages_use_the_default_channel_and_provider_alone_is_enough(): void
    {
        Http::fake([self::PROVIDER => Http::response('sent', 200)]);
        $this->connectProvider(['sms_default_channel' => 'provider', 'sms_auto_absence' => '1']);
        $this->assertSame(['provider'], array_keys(app(SmsService::class)->channels()));
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher);
        $student = $this->studentIn($circle, ['status' => 'active']);
        $parent = $this->parentUser();
        $parent->forceFill(['phone' => '0599111222'])->save();
        $student->parents()->attach($parent->id, ['relationship' => 'أم']);

        app(AttendanceService::class)->record($circle, now()->toDateString(), [$student->id => ['status' => 'absent']], $teacher);
        Http::assertSent(fn ($r) => $r['to'] === '970599111222' && str_contains($r['message'], 'بغياب '.$student->full_name));
        $this->assertSame('provider', SmsMessage::firstOrFail()->channel);
        // Admins can see the SMS page once any channel is connected.
        $this->actingAs($this->admin())->get(route('admin.sms.index'))->assertOk();
    }
}
