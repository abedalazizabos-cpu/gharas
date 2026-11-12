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
    private static bool $unreachable = false;

    public function configured(): bool
    {
        return Setting::get('sms_enabled') === '1' && Setting::get('sms_username') && Setting::get('sms_password');
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
    public function send(iterable $phones, string $text, string $purpose = 'manual', ?User $by = null): array
    {
        $numbers = collect($phones)->map(fn ($p) => $this->normalize($p))->filter()->unique()->values();
        $result = ['sent' => 0, 'failed' => 0, 'error' => null];
        if ($numbers->isEmpty() || ! $this->configured()) {
            $result['error'] = $numbers->isEmpty() ? 'لا توجد أرقام صالحة.' : 'إرسال الرسائل غير مفعّل في الإعدادات.';

            return $result;
        }
        $text = $this->withSignature($text);

        foreach ($numbers->chunk(self::CHUNK) as $chunk) {
            $error = null;
            $gatewayId = null;
            if (self::$unreachable) {
                $error = 'تعذّر الاتصال ببوابة الرسائل.';
            } else {
                try {
                    $response = Http::withBasicAuth((string) Setting::get('sms_username'), $this->password())
                        ->acceptJson()->connectTimeout(5)->timeout(15)
                        ->post(Setting::get('sms_url') ?: self::DEFAULT_URL, [
                            'textMessage' => ['text' => $text],
                            'phoneNumbers' => $chunk->values()->all(),
                        ]);
                    if ($response->successful()) {
                        $gatewayId = $response->json('id');
                    } else {
                        $error = match ($response->status()) {
                            401, 403 => 'اسم المستخدم أو كلمة المرور في إعدادات الرسائل غير صحيحة.',
                            503 => 'الجوال لم يستلم الرسائل بعد (تأكد أنه مشغّل ومتصل بالإنترنت).',
                            default => 'رفض البوابة: '.$response->status().' '.mb_substr((string) ($response->json('message') ?? $response->body()), 0, 120),
                        };
                    }
                } catch (\Throwable $e) {
                    Log::warning('SMS gateway error: '.$e->getMessage());
                    $error = 'تعذّر الاتصال ببوابة الرسائل.';
                    self::$unreachable = true;
                }
            }

            SmsMessage::create([
                'purpose' => $purpose, 'text' => $text, 'phones' => $chunk->values()->all(), 'phones_count' => $chunk->count(),
                'status' => $error ? 'failed' : 'sent', 'error' => $error, 'gateway_id' => $gatewayId, 'sent_by' => $by?->id,
            ]);
            $error ? $result['failed'] += $chunk->count() : $result['sent'] += $chunk->count();
            $result['error'] ??= $error;
        }

        return $result;
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
