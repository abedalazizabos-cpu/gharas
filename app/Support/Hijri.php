<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Hijri (Umm al-Qura) dates shown beside the Gregorian ones. Uses PHP's intl extension when it is
 * installed; otherwise a tabular calculation, which can differ from the official calendar by a day.
 */
class Hijri
{
    public const MONTHS = [1 => 'محرم', 'صفر', 'ربيع الأول', 'ربيع الآخر', 'جمادى الأولى', 'جمادى الآخرة', 'رجب', 'شعبان', 'رمضان', 'شوال', 'ذو القعدة', 'ذو الحجة'];

    public static function enabled(): bool
    {
        return Setting::get('hijri_dates', '1') === '1';
    }

    /** @return array{0: int, 1: int, 2: int} day, month, year */
    public static function parts(CarbonInterface|string $date): array
    {
        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date);
        if (class_exists(\IntlDateFormatter::class)) {
            $f = new \IntlDateFormatter('en_US@calendar=islamic-umalqura', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE,
                'UTC', \IntlDateFormatter::TRADITIONAL, 'd/M/y');
            $out = $f->format(gmmktime(12, 0, 0, $date->month, $date->day, $date->year));
            if ($out && preg_match('/^(\d+)\/(\d+)\/(\d+)/', $out, $m)) {
                return [(int) $m[1], (int) $m[2], (int) $m[3]];
            }
        }

        return self::tabular($date->year, $date->month, $date->day);
    }

    /** "2 جمادى الآخرة 1448هـ" */
    public static function format(CarbonInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        [$d, $m, $y] = self::parts($date);

        return $d.' '.self::MONTHS[$m].' '.$y.'هـ';
    }

    /** "1448/06/02هـ" */
    public static function short(CarbonInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        [$d, $m, $y] = self::parts($date);

        return $y.'/'.str_pad((string) $m, 2, '0', STR_PAD_LEFT).'/'.str_pad((string) $d, 2, '0', STR_PAD_LEFT).'هـ';
    }

    /** "2026/11/12 - 2 جمادى الآخرة 1448هـ" (or the Gregorian date alone when Hijri dates are off). */
    public static function both(CarbonInterface|string|null $date, string $format = 'Y/m/d'): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        return $date->format($format).(self::enabled() ? ' - '.self::format($date) : '');
    }

    /** Islamic tabular (civil) calendar. */
    private static function tabular(int $y, int $m, int $d): array
    {
        $a = intdiv(14 - $m, 12);
        $yy = $y + 4800 - $a;
        $mm = $m + 12 * $a - 3;
        $jd = $d + intdiv(153 * $mm + 2, 5) + 365 * $yy + intdiv($yy, 4) - intdiv($yy, 100) + intdiv($yy, 400) - 32045;
        $l = $jd - 1948440 + 10632;
        $n = intdiv($l - 1, 10631);
        $l = $l - 10631 * $n + 354;
        $j = intdiv(10985 - $l, 5316) * intdiv(50 * $l, 17719) + intdiv($l, 5670) * intdiv(43 * $l, 15238);
        $l = $l - intdiv(30 - $j, 15) * intdiv(17719 * $j, 50) - intdiv($j, 16) * intdiv(15238 * $j, 43) + 29;
        $month = intdiv(24 * $l, 709);
        $day = $l - intdiv(709 * $month, 24);
        $year = 30 * $n + $j - 30;

        return [$day, $month, $year];
    }
}
