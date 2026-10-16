<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Production-safe seeders always run. Demo data (fake Arabic accounts and
     * records) only runs outside production.
     */
    public function run(): void
    {
        $this->call([SurahSeeder::class, SettingSeeder::class]);

        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
