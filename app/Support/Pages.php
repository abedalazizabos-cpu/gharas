<?php

namespace App\Support;

/** Formats page amounts (memorization is measured in pages, in quarters of a page). */
class Pages
{
    /** 12.50 → "12.5", 3.00 → "3". */
    public static function num(float|string|null $pages): string
    {
        return rtrim(rtrim(number_format((float) $pages, 2, '.', ''), '0'), '.');
    }
}
