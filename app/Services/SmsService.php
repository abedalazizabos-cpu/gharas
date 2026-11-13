<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SmsMessage;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS to parents, sent from the center's own mobile number: an Android phone with the free
 * «SMS Gateway for Android» app (sms-gate.app) receives each message from the site and sends it
 * from its SIM card. Settings hold the gateway address and the app's username and password.
 */
class SmsService
{
    public const DEFAULT_URL = 'https://api.sms-gate.app/3rdparty/v1/messages';

    /** Numbers per request; the phone sends them one after another. */
    private const CHUNK = 50;

    /** Automatic messages the super admin can switch on or off. */
    public const AUTO = ['absence' => 'عند تسجيل غياب الطالب', 'day_off' => 'عندما يعلن المحفظ «لا تحفيظ اليوم»', 'certificate' => 'عند صدور شهادة للطالب', 'monthly' => 'رابط التقرير الشهري للطالب (أول كل شهر)', 'registration' => 'عند قبول طلب تسجيل طالب جديد (مع بيانات الدخول)'];

    /** Set after the gateway could not be reached, so one request does not wait on it again and again. */
    private static array $unreachable = [];

    /** Forgets that a way of sending was unreachable (new request, or between tests). */
    public static function resetState(): void
    {
        self::$unreachable = [];
    }

    /** Ways of sending: the center's own phone, or a paid SMS provider (messages show the center's name). */
    public const CHANNELS = ['phone' => 'جوال المركز', 'provider' => 'مزوّد الرسائل'];

    /** Number formats providers ask for. */
    public const NUMBER_FORMATS = ['intl' => '970599123456 (رمز الدولة بلا +)', 'plus' => '+970599123456', 'local' => '0599123456 (محلي)'];

    public function phoneConfigured(): bool
    {
        return Setting::get('sms_enabled') === '1' && Setting::get('sms_username') && Setting::get('sms_password');
    }

    public function providerConfigured(): bool
    {
        return Setting::get('sms_provider_enabled') === '1' && filled(Setting::get('sms_provider_url'));
    }

    /** @return array<string, string> the ways that are set up and switched on */
    public function channels(): array
    {
        return array_filter(self::CHANNELS, fn ($k) => $k === 'phone' ? $this->phoneConfigured() : $this->providerConfigured(), ARRAY_FILTER_USE_KEY);
    }

    public function configured(): bool
    {
        return $this->channels() !== [];
    }

    /** The way used when none is chosen (automatic messages): the super admin's choice, if it is set up. */
    public function defaultChannel(): ?string
    {
        $channels = $this->channels();
        $chosen = Setting::get('sms_default_channel');

        return isset($channels[$chosen]) ? $chosen : (array_key_first($channels) ?: null);
    }

    public function autoEnabled(string $purpose): bool
    {
        return $this->configured() && Setting::get('sms_auto_'.$purpose) === '1';
    }

    /** 0599123456 → +970599123456 (the country code comes from the settings). */
    public function normalize(?string $phone): ?string
    {
        $digits = User::normalizePhone($phone);
        if (! $digits) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $digits);
        $code = ltrim(Setting::get('sms_country_code') ?: '970', '+');

        return match (true) {
            str_starts_with((string) $phone, '+') => '+'.$digits,
            str_starts_with($digits, '00') => '+'.substr($digits, 2),
            str_starts_with($digits, '0') => '+'.$code.substr($digits, 1),
            str_starts_with($digits, $code) && strlen($digits) > 10 => '+'.$digits,
            default => '+'.$code.$digits,
        };
    }

    /** The message text with the center's signature line, if one is set. */
    public function withSignature(string $text): string
    {
        $sign = trim((string) Setting::get('sms_signature'));

        return $sign !== '' && ! str_ends_with(trim($text), $sign) ? trim($text)."\n".$sign : trim($text);
    }

    /**
     * Sends one text to many numbers. Never throws: failures are recorded and counted.
     *
     * @param  iterable<string|null>  $phones
     * @return array{sent: int, failed: int, error: ?string}
     */
    public function send(iterable $phones, string $text, string $purpose = 'manual', ?User $by = null, ?string $channel = null): array
    {
        $numbers = collect($phones)->map(fn ($p) => $this->normalize($p))->filter()->unique()->values();
        $result = ['sent' => 0, 'failed' => 0, 'error' => null];
        $channel = $channel && isset($this->channels()[$channel]) ? $channel : $this->defaultChannel();
        if ($numbers->isEmpty() || ! $channel) {
            $result['error'] = $numbers->isEmpty() ? 'لا توجد أرقام صالحة.' : 'إرسال الرسائل غير مفعّل في الإعدادات.';

            return $result;
        }
        $text = $this->withSignature($text);

        foreach ($numbers->chunk(self::CHUNK) as $chunk) {
            $chunk = $chunk->values();
            [$error, $gatewayId] = self::$unreachable[$channel] ?? false
                ? ['تعذّر الاتصال ب'.($channel === 'phone' ? 'بوابة الرسائل.' : 'مزوّد الرسائل.'), null]
                : ($channel === 'phone' ? $this->viaPhone($chunk->all(), $text) : $this->viaProvider($chunk->all(), $text));

            SmsMessage::create([
                'purpose' => $purpose, 'channel' => $channel, 'text' => $text, 'phones' => $chunk->all(), 'phones_count' => $chunk->count(),
                'status' => $error ? 'failed' : 'sent', 'error' => $error, 'gateway_id' => $gatewayId, 'sent_by' => $by?->id,
            ]);
            $error ? $result['failed'] += $chunk->count() : $result['sent'] += $chunk->count();
            $result['error'] ??= $error;
        }

        return $result;
    }

    /** The center's Android phone (SMS Gateway app): one request for many numbers. @return array{0: ?string, 1: ?string} error, id */
    private function viaPhone(array $numbers, string $text): array
    {
        try {
            $response = Http::withBasicAuth((string) Setting::get('sms_username'), $this->password())
                ->acceptJson()->connectTimeout(5)->timeout(15)
                ->post(Setting::get('sms_url') ?: self::DEFAULT_URL, ['textMessage' => ['text' => $text], 'phoneNumbers' => $numbers]);
            if ($response->successful()) {
                return [null, $response->json('id')];
            }

            return [match ($response->status()) {
                401, 403 => 'اسم المستخدم أو كلمة المرور في إعدادات الرسائل غير صحيحة.',
                503 => 'الجوال لم يستلم الرسائل بعد (تأكد أنه مشغّل ومتصل بالإنترنت).',
                default => 'رفض البوابة: '.$response->status().' '.mb_substr((string) ($response->json('message') ?? $response->body()), 0, 120),
            }, null];
        } catch (\Throwable $e) {
            Log::warning('SMS gateway error: '.$e->getMessage());
            self::$unreachable['phone'] = true;

            return ['تعذّر الاتصال ببوابة الرسائل.', null];
        }
    }

    /**
     * A paid SMS provider through its HTTP link (most local providers work this way): the parameter
     * names, the sender name, the key and the number format come from the settings.
     *
     * @return array{0: ?string, 1: ?string} error, provider reply
     */
    private function viaProvider(array $numbers, string $text): array
    {
        $format = Setting::get('sms_provider_number_format') ?: 'intl';
        $numbers = array_map(fn ($n) => match ($format) {
            'plus' => $n,
            'local' => '0'.substr($n, strlen(ltrim(Setting::get('sms_country_code') ?: '+970', '+')) + 1),
            default => ltrim($n, '+'),
        }, $numbers);
        $groups = Setting::get('sms_provider_bulk') === '1' ? [implode(',', $numbers)] : $numbers;
        $base = $this->providerExtraParams();
        foreach (['sms_provider_sender_param' => 'sms_provider_sender', 'sms_provider_key_param' => null] as $nameKey => $valueKey) {
            $name = trim((string) Setting::get($nameKey));
            $value = $valueKey ? Setting::get($valueKey) : $this->providerKey();
            if ($name !== '' && filled($value)) {
                $base[$name] = $value;
            }
        }
        $toParam = Setting::get('sms_provider_to_param') ?: 'to';
        $textParam = Setting::get('sms_provider_text_param') ?: 'message';
        $expect = trim((string) Setting::get('sms_provider_success'));
        $replies = [];

        foreach ($groups as $to) {
            $params = $base + [$toParam => $to, $textParam => $text];
            try {
                $http = Http::connectTimeout(5)->timeout(20);
                $response = Setting::get('sms_provider_method') === 'GET'
                    ? $http->get(Setting::get('sms_provider_url'), $params)
                    : $http->asForm()->post(Setting::get('sms_provider_url'), $params);
                $body = mb_substr(trim((string) $response->body()), 0, 120);
                if (! $response->successful() || ($expect !== '' && ! str_contains((string) $response->body(), $expect))) {
                    return ['رفض مزوّد الرسائل: '.$response->status().' '.$body, null];
                }
                $replies[] = $body;
            } catch (\Throwable $e) {
                Log::warning('SMS provider error: '.$e->getMessage());
                self::$unreachable['provider'] = true;

                return ['تعذّر الاتصال بمزوّد الرسائل.', null];
            }
        }

        return [null, mb_substr(implode(' | ', $replies), 0, 190) ?: null];
    }

    /** Fixed parameters some providers need (one "name=value" per line). */
    private function providerExtraParams(): array
    {
        $params = [];
        foreach (preg_split('/\R/', (string) Setting::get('sms_provider_extra')) as $line) {
            if (str_contains($line, '=')) {
                [$k, $v] = array_map('trim', explode('=', $line, 2));
                if ($k !== '') {
                    $params[$k] = $v;
                }
            }
        }

        return $params;
    }

    public function providerKey(): string
    {
        $stored = (string) Setting::get('sms_provider_key');
        try {
            return $stored === '' ? '' : Crypt::decryptString($stored);
        } catch (\Throwable) {
            return '';
        }
    }

    /** Automatic message to a student's parents, if that kind is switched on. */
    public function toParents(Student $student, string $text, string $purpose): void
    {
        if ($this->autoEnabled($purpose)) {
            $this->send($student->parents->pluck('phone'), $text, $purpose);
        }
    }

    public function password(): string
    {
        $stored = (string) Setting::get('sms_password');
        try {
            return $stored === '' ? '' : Crypt::decryptString($stored);
        } catch (\Throwable) {
            return '';
        }
    }

    /** Counts the SMS parts a text needs (Arabic: 70 characters, or 67 per part when longer). */
    public static function parts(string $text): int
    {
        $len = mb_strlen($text);
        $unicode = (bool) preg_match('/[^\x00-\x7F]/', $text);
        [$single, $multi] = $unicode ? [70, 67] : [160, 153];

        return $len <= $single ? 1 : (int) ceil($len / $multi);
    }
}
