<?php

namespace Database\Seeders;

use App\Models\AssessmentCriterion;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Setting::DEFAULTS as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }

        $criteria = [['جودة الحفظ', 40], ['أحكام التجويد', 25], ['جودة التلاوة ومخارج الحروف', 20], ['المراجعة والتثبيت', 15]];
        foreach ($criteria as $i => [$name, $max]) {
            AssessmentCriterion::firstOrCreate(['name' => $name], ['max_score' => $max, 'sort_order' => $i + 1, 'is_active' => true]);
        }
    }
}
