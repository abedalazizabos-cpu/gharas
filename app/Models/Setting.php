<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'center_name' => 'مركز غراس لتحفيظ القرآن الكريم',
        'center_about' => 'مركز تعليمي يعنى بتحفيظ كتاب الله تعالى وتجويده وغرس القيم القرآنية في نفوس الناشئة.',
        'center_mission' => 'إعداد جيل حافظ لكتاب الله، متقن لتلاوته، عامل بأحكامه.',
        'center_goals' => "تحفيظ القرآن الكريم بإتقان\nتعليم أحكام التجويد\nغرس القيم والأخلاق الإسلامية\nإشراك الأسرة في متابعة الأبناء",
        'contact_phone' => '0500000000',
        'contact_email' => 'info@gharas.test',
        'contact_address' => 'المملكة العربية السعودية',
        'pass_score' => '70',
        'teacher_edit_days' => '7',
        'allow_capacity_override' => '1',
        'center_logo' => null,
        // Brand (super admin)
        'brand_title' => 'مركز غراس',
        'brand_subtitle' => 'لتحفيظ القرآن الكريم',
        'logo_size' => 'medium',
        'logo_shape' => 'rounded',
        'logo_background' => '1',
        'logo_on_pdf' => '1',
        'logo_favicon' => '1',
        'theme_color' => '#1b6b50',
        // Reminders
        'reminders_enabled' => '1',
        'reminders_interval' => '3',
        'reminders_duration' => '15',
        'reminders_from' => '00:00',
        'reminders_to' => '23:59',
        'reminders_days' => '0,1,2,3,4,5,6',
        'reminders_roles' => 'superadmin,admin,teacher,parent,visitor',
        'reminders_order' => 'sequential',
        'reminders_position' => 'bottom',
        'reminders_on_login' => '0',
        'reminders_text' => self::DEFAULT_REMINDERS,
    ];

    /** One reminder per line; text after « | » is shown as its source. */
    public const DEFAULT_REMINDERS = <<<'TXT'
    ﴿وَلَقَدْ يَسَّرْنَا الْقُرْآنَ لِلذِّكْرِ فَهَلْ مِن مُّدَّكِرٍ﴾ | سورة القمر: 17
    ﴿إِنَّا نَحْنُ نَزَّلْنَا الذِّكْرَ وَإِنَّا لَهُ لَحَافِظُونَ﴾ | سورة الحجر: 9
    ﴿وَقُل رَّبِّ زِدْنِي عِلْمًا﴾ | سورة طه: 114
    ﴿أَلَا بِذِكْرِ اللَّهِ تَطْمَئِنُّ الْقُلُوبُ﴾ | سورة الرعد: 28
    ﴿رَبِّ اشْرَحْ لِي صَدْرِي ۝ وَيَسِّرْ لِي أَمْرِي﴾ | سورة طه: 25-26
    ﴿إِنَّ هَٰذَا الْقُرْآنَ يَهْدِي لِلَّتِي هِيَ أَقْوَمُ﴾ | سورة الإسراء: 9
    ﴿وَرَتِّلِ الْقُرْآنَ تَرْتِيلًا﴾ | سورة المزمل: 4
    ﴿فَاذْكُرُونِي أَذْكُرْكُمْ وَاشْكُرُوا لِي وَلَا تَكْفُرُونِ﴾ | سورة البقرة: 152
    ﴿رَبَّنَا آتِنَا فِي الدُّنْيَا حَسَنَةً وَفِي الْآخِرَةِ حَسَنَةً وَقِنَا عَذَابَ النَّارِ﴾ | سورة البقرة: 201
    «خيركم من تعلّم القرآن وعلّمه» | رواه البخاري
    «اقرؤوا القرآن فإنه يأتي يوم القيامة شفيعاً لأصحابه» | رواه مسلم
    «الماهر بالقرآن مع السفرة الكرام البررة» | متفق عليه
    اللهم اجعل القرآن ربيع قلوبنا، ونور صدورنا، وجلاء أحزاننا، وذهاب همومنا | دعاء
    سبحان الله وبحمده، سبحان الله العظيم | ذكر
    لا حول ولا قوة إلا بالله | ذكر
    اللهم صلِّ وسلم على نبينا محمد | ذكر
    اللهم ارحم شهداءنا، واغفر لهم، وارفع درجاتهم في عليين | دعاء للشهداء
    اللهم تقبّل شهداءنا، وأسكنهم الفردوس الأعلى، واجبر قلوب أهليهم | دعاء للشهداء
    TXT;

    public const LOGO_SIZES = ['small' => ['صغير', 34], 'medium' => ['متوسط', 42], 'large' => ['كبير', 56]];
    public const LOGO_SHAPES = ['rounded' => 'زوايا مستديرة', 'circle' => 'دائري', 'square' => 'مربع'];
    public const THEME_COLORS = ['#1b6b50' => 'أخضر غراس', '#0f5c7a' => 'أزرق', '#6b4a1b' => 'بني', '#5b3f8c' => 'بنفسجي', '#8a1c2b' => 'عنابي', '#2f3e46' => 'رمادي داكن'];

    /** Settings for the reminder toast in the browser, or null when this user should not see reminders. */
    public static function reminderConfig(?User $user): ?array
    {
        if (! $user || static::get('reminders_enabled') !== '1' || ! in_array($user->role, explode(',', (string) static::get('reminders_roles')), true)) {
            return null;
        }

        return [
            'interval' => max(1, (int) static::get('reminders_interval')),
            'duration' => min(120, max(5, (int) static::get('reminders_duration'))),
            'from' => static::get('reminders_from') ?: '00:00',
            'to' => static::get('reminders_to') ?: '23:59',
            'days' => array_map('intval', array_filter(explode(',', (string) static::get('reminders_days')), 'strlen')),
            'order' => static::get('reminders_order') ?: 'sequential',
            'position' => static::get('reminders_position') ?: 'bottom',
            'onLogin' => static::get('reminders_on_login') === '1',
            'items' => static::reminders(),
        ];
    }

    /** Reminders for the in-app toast, as [['text' => ..., 'source' => ...], ...]. */
    public static function reminders(): array
    {
        return collect(preg_split('/\R/u', (string) static::get('reminders_text')))
            ->map(fn ($line) => array_map('trim', explode('|', $line, 2)))
            ->filter(fn ($parts) => $parts[0] !== '')
            ->map(fn ($parts) => ['text' => $parts[0], 'source' => $parts[1] ?? ''])
            ->values()->all();
    }

    /** Settings already read in this request, so each page reads the cache once rather than once per setting. */
    private static ?array $loaded = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = static::$loaded ??= Cache::rememberForever('settings.all', fn () => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.all');
        static::$loaded = null;
    }
}
