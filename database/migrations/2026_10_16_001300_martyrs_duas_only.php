<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/** Adds the duas for the martyrs to an already-saved reminders list (new installs get them from the defaults). */
return new class extends Migration
{
    private const LINES = [
        'اللهم ارحم شهداءنا، واغفر لهم، وارفع درجاتهم في عليين | دعاء للشهداء',
        'اللهم تقبّل شهداءنا، وأسكنهم الفردوس الأعلى، واجبر قلوب أهليهم | دعاء للشهداء',
    ];

    public function up(): void
    {
        $current = Setting::query()->where('key', 'reminders_text')->value('value');
        if ($current === null) {
            return;
        }
        // Only duas for the martyrs, no verse: drop the verse if an earlier copy of this migration added it.
        $current = implode("\n", array_filter(explode("\n", $current), fn ($l) => ! str_contains($l, 'سورة آل عمران: 169')));
        if (! str_contains($current, 'شهداءنا')) {
            $current = rtrim($current)."\n".implode("\n", self::LINES);
        }
        Setting::put('reminders_text', $current);
    }

    public function down(): void {}
};
