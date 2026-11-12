<?php

namespace Database\Seeders;

use App\Models\RegistrationRequest;
use Illuminate\Database\Seeder;

/** Trial registration requests from the website, waiting for the admin. */
class ExtrasDemoSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['عبدالرحمن خالد الزهراني', 'male', '2015-03-14', 3, 'خالد الزهراني', '0591000101', 'father', 'يفضّل حلقة بعد العصر.', 2],
            ['مريم سامي النجار', 'female', '2014-09-02', 5, 'سامي النجار', '0591000102', 'father', null, 1],
            ['حمزة يوسف عودة', 'male', '2017-01-20', 0, 'أم حمزة', '0591000103', 'mother', 'أول مرة يلتحق بحلقة تحفيظ.', 1],
            ['ليان أحمد الشريف', 'female', '2013-06-11', 8, 'أحمد الشريف', '0591000104', 'father', 'أخوها مسجل في المركز.', 0],
            ['إبراهيم محمود صبح', 'male', '2016-11-30', 1, 'محمود صبح', '0591000105', 'father', null, 0],
        ];
        foreach ($rows as [$name, $gender, $birth, $juz, $guardian, $phone, $rel, $notes, $daysAgo]) {
            $r = RegistrationRequest::firstOrCreate(['student_name' => $name, 'guardian_phone' => $phone], [
                'gender' => $gender, 'birth_date' => $birth, 'memorized_juz' => $juz, 'guardian_name' => $guardian, 'relationship' => $rel, 'notes' => $notes,
            ]);
            $r->forceFill(['created_at' => now()->subDays($daysAgo)->subHours(3)])->save();
        }
    }
}
