<?php

namespace App\Support;

use App\Models\Setting;
use Shuchkin\SimpleXLSXGen;

/**
 * Excel (xlsx) copy of a report: the same title and tables as its PDF, right-to-left,
 * so the center can edit or forward the numbers. Certificates stay PDF only.
 */
class ExcelExport
{
    /**
     * @param  array<int, array{title?: string, headers: array<int, string>, rows: iterable, empty?: string}>  $sections
     */
    public static function render(string $title, ?string $subtitle, array $sections): string
    {
        $bold = fn (string $v) => '<b>'.self::escape($v).'</b>';
        $rows = [
            [$bold(Setting::get('center_name', 'مركز غراس لتحفيظ القرآن الكريم'))],
            ['<style font-size="14"><b>'.self::escape($title).'</b></style>'],
        ];
        if ($subtitle) {
            $rows[] = [self::cell($subtitle)];
        }
        $rows[] = [self::cell('طُبع في '.now()->format('Y/m/d H:i').(Hijri::enabled() ? ' - '.Hijri::format(now()) : ''))];
        $width = 1;

        foreach ($sections as $section) {
            $rows[] = [];
            if (! empty($section['title'])) {
                $rows[] = [$bold($section['title'])];
            }
            $data = collect($section['rows'])->map(fn ($r) => array_values((array) $r))->values();
            if ($data->isEmpty()) {
                $rows[] = [self::cell($section['empty'] ?? 'لا توجد بيانات.')];

                continue;
            }
            if ($section['headers']) {
                $rows[] = array_map(fn ($h) => '<style bgcolor="#E6EFEA"><b>'.self::escape((string) $h).'</b></style>', $section['headers']);
            }
            foreach ($data as $r) {
                $rows[] = array_map([self::class, 'cell'], $r);
            }
            $width = max($width, count($section['headers']), $data->max(fn ($r) => count($r)));
        }

        $xlsx = SimpleXLSXGen::fromArray($rows, mb_substr(preg_replace('/[\\\\\/?*:\[\]]/u', ' ', $title), 0, 31))
            ->rightToLeft()
            ->setDefaultFont('Arial')
            ->setTitle($title);
        for ($c = 1; $c <= $width; $c++) {
            $xlsx->setColWidth($c, $c === 1 ? 28 : 16);
        }

        return (string) $xlsx;
    }

    /** Numbers stay numbers (so Excel can add them up); every text is written as plain text, never a formula. */
    private static function cell($v)
    {
        if (is_int($v) || is_float($v)) {
            return $v;
        }
        $v = (string) $v;
        if (preg_match('/^-?\d+(\.\d+)?$/', $v) && ! preg_match('/^0\d/', $v)) {
            return $v + 0;
        }

        return "\0".$v;
    }

    private static function escape(string $v): string
    {
        return str_replace(['<', '>'], ['‹', '›'], $v);
    }
}
