<?php

namespace Database\Seeders;

use App\Models\Certificate;
use App\Models\QualityTest;
use App\Models\SunnahEnrollment;
use App\Models\User;
use App\Services\CertificateService;
use Illuminate\Database\Seeder;

/**
 * Trial certificates: most passed courses and quality tests already have one, issued a few days after
 * the result, and some are left for the admin to issue from «الشهادات». Parent 0550000001's two
 * children have certificates of every kind they passed.
 */
class CertificateDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::admins()->where('role', User::ROLE_ADMIN)->orderBy('id')->first() ?? User::admins()->orderBy('id')->first();
        if (! $admin) {
            return;
        }
        $showcase = User::role(User::ROLE_PARENT)->where('phone', '0550000001')->first()?->children()->pluck('students.id') ?? collect();
        mt_srand(4242);

        $items = SunnahEnrollment::where('status', 'passed')->whereDoesntHave('certificate')->get()
            ->map(fn ($e) => ['key' => 'course:'.$e->id, 'student' => $e->student_id, 'on' => $e->evaluated_on])
            ->merge(QualityTest::passed()->whereDoesntHave('certificate')->get()
                ->map(fn ($t) => ['key' => 'test:'.$t->id, 'student' => $t->student_id, 'on' => $t->tested_on]))
            ->filter(fn ($i) => $i['on'])
            ->sortBy(fn ($i) => $i['on']->toDateString())->values();

        $service = app(CertificateService::class);
        $picked = $items->map(function ($item) use ($showcase) {
            $mine = $showcase->contains($item['student']);
            if (! $mine && mt_rand(1, 100) > 75) {
                return null;
            }
            $on = $item['on']->copy()->addDays(mt_rand(2, 9));

            return $item + ['mine' => $mine, 'issued_on' => ($on->isFuture() ? now() : $on)->toDateString()];
        })->filter()->sortBy('issued_on');

        // One issue per day, as the admin would do it.
        $groups = $picked->groupBy(fn ($i) => $i['issued_on'].($i['mine'] ? '+' : ''));
        // Only the newest certificate of the trial parent comes with a notification, so the other trial notifications stay visible.
        $newestMine = $groups->keys()->filter(fn ($k) => str_ends_with($k, '+'))->last();
        foreach ($groups as $key => $group) {
            $service->issue($group->pluck('key')->all(), $admin, $group->first()['issued_on'], notify: $key === $newestMine);
        }
    }

    public static function clear(): void
    {
        Certificate::query()->delete();
    }
}
