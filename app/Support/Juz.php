<?php

namespace App\Support;

/**
 * The 30 ajza of the mushaf (Madani print, Hafs). Students at the center start from juz 30
 * (عمّ) and go down, so "the next juz" is always the one with the smaller number.
 */
class Juz
{
    /** juz => [surah, ayah] where the juz starts. */
    public const STARTS = [
        1 => [1, 1], 2 => [2, 142], 3 => [2, 253], 4 => [3, 93], 5 => [4, 24],
        6 => [4, 148], 7 => [5, 82], 8 => [6, 111], 9 => [7, 88], 10 => [8, 41],
        11 => [9, 93], 12 => [11, 6], 13 => [12, 53], 14 => [15, 1], 15 => [17, 1],
        16 => [18, 75], 17 => [21, 1], 18 => [23, 1], 19 => [25, 21], 20 => [27, 56],
        21 => [29, 46], 22 => [33, 31], 23 => [36, 28], 24 => [39, 32], 25 => [41, 47],
        26 => [46, 1], 27 => [51, 31], 28 => [58, 1], 29 => [67, 1], 30 => [78, 1],
    ];

    /** Names people use for the last ajza. */
    public const NAMES = [30 => 'عمّ', 29 => 'تبارك', 28 => 'قد سمع', 27 => 'الذاريات', 26 => 'الأحقاف'];

    /** The juz that holds this ayah. */
    public static function of(int $surah, int $ayah = 1): int
    {
        for ($juz = 30; $juz >= 1; $juz--) {
            [$s, $a] = self::STARTS[$juz];
            if ($surah > $s || ($surah === $s && $ayah >= $a)) {
                return $juz;
            }
        }

        return 1;
    }

    public static function label(int $juz): string
    {
        return 'الجزء '.$juz.(isset(self::NAMES[$juz]) ? ' ('.self::NAMES[$juz].')' : '');
    }

    /** "30 - 28" written from the higher juz down, as the center says it. */
    public static function rangeLabel(int $a, int $b): string
    {
        return $a === $b ? self::label($a) : 'الأجزاء '.max($a, $b).' - '.min($a, $b);
    }

    /** "جزء واحد"، "جزآن"، "5 أجزاء"، "12 جزءاً" */
    public static function count(int $n): string
    {
        return match (true) {
            $n === 0 => 'لا شيء',
            $n === 1 => 'جزء واحد',
            $n === 2 => 'جزآن',
            $n <= 10 => $n.' أجزاء',
            default => $n.' جزءاً',
        };
    }

    /** juz number => label, for select boxes (30 first). */
    public static function options(): array
    {
        $out = [];
        for ($j = 30; $j >= 1; $j--) {
            $out[$j] = self::label($j);
        }

        return $out;
    }
}
