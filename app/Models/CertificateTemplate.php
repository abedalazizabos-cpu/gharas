<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Wording of a certificate, written by the admin. Placeholders like {الطالب} are filled when issued. */
class CertificateTemplate extends Model
{
    public const KINDS = ['course' => 'شهادة دورة (قسم السنة)', 'individual' => 'شهادة اختبار منفرد', 'combined' => 'شهادة اختبار مجتمع'];

    public const PLACEHOLDERS = [
        '{الطالب}' => 'اسم الطالب', '{اللقب}' => 'الطالب أو الطالبة', '{له}' => 'له أو لها', '{ه}' => 'ه أو ها (يجعله / يجعلها)', '{ت}' => 'تاء التأنيث (أتمّ / أتمّت)', '{المركز}' => 'اسم المركز',
        '{الحلقة}' => 'حلقة الطالب', '{التقدير}' => 'التقدير', '{النسبة}' => 'النسبة', '{التاريخ}' => 'تاريخ الإصدار',
        '{رقم_الشهادة}' => 'رقم الشهادة', '{التاريخ_الهجري}' => 'تاريخ الإصدار بالهجري', '{الدورة}' => 'اسم الدورة (للدورات)', '{الشيخ}' => 'شيخ الدورة (للدورات)',
        '{الجزء}' => 'الجزء (للمنفرد)', '{الأجزاء}' => 'الأجزاء (للمجتمع)', '{المختبر}' => 'شيخ الجودة (للاختبارات)',
    ];

    public const COLORS = ['#1b6b50' => 'أخضر', '#2f5fb3' => 'أزرق', '#b8892b' => 'ذهبي', '#7a3e9d' => 'بنفسجي', '#8b1e3f' => 'عنابي', '#0F3D2E' => 'أخضر داكن'];

    protected $fillable = ['title', 'intro', 'body', 'closing', 'signer1_title', 'signer1_name', 'signer2_title', 'signer2_name', 'footer', 'color'];

    public static function for(string $kind): self
    {
        return static::where('kind', $kind)->firstOrFail();
    }

    public function label(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    /** @param array<string, string> $values placeholder => value */
    public function render(array $values): array
    {
        $fill = fn (?string $text) => $text === null || $text === '' ? null : strtr($text, $values);

        return [
            'title' => $fill($this->title), 'intro' => $fill($this->intro), 'body' => $fill($this->body), 'closing' => $fill($this->closing),
            'signer1_title' => $fill($this->signer1_title), 'signer1_name' => $fill($this->signer1_name),
            'signer2_title' => $fill($this->signer2_title), 'signer2_name' => $fill($this->signer2_name),
            'footer' => $fill($this->footer), 'color' => $this->color,
        ];
    }
}
