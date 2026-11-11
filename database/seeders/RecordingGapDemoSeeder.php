<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Circle;
use App\Models\CircleDayOff;
use App\Models\CircleSession;
use App\Models\MemorizationRecord;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Trial data for «متابعة تسجيل المحفظين»: a few teachers missed recording some recent days, so the
 * alerts show on their dashboards and on the admin's. teacher1 missed the attendance and recitation
 * of his first circle on its latest finished study day; one teacher said a missed day was a sick day.
 */
class RecordingGapDemoSeeder extends Seeder
{
    public function run(): void
    {
        $teachers = User::role(User::ROLE_TEACHER)->whereIn('email', ['teacher1@gharas.test', 'teacher4@gharas.test', 'teacher9@gharas.test', 'teacher13@gharas.test', 'teacher18@gharas.test'])
            ->get()->keyBy('email');

        // [teacher, which past study day (1 = latest), what is missing]
        $plan = [
            ['teacher1@gharas.test', 1, 'both'],
            ['teacher4@gharas.test', 1, 'memorization'],
            ['teacher4@gharas.test', 3, 'both'],
            ['teacher9@gharas.test', 2, 'attendance'],
            ['teacher13@gharas.test', 4, 'both'],
            ['teacher18@gharas.test', 2, 'both', 'مرض المحفظ'],
        ];
        foreach ($plan as $row) {
            [$email, $nth, $what] = $row;
            $teacher = $teachers[$email] ?? null;
            $circle = $teacher ? Circle::active()->where('teacher_id', $teacher->id)->orderBy('id')->first() : null;
            if (! $circle) {
                continue;
            }
            $day = $this->pastStudyDay($circle, $nth);
            if (in_array($what, ['both', 'attendance'], true)) {
                $sessions = CircleSession::where('circle_id', $circle->id)->whereDate('session_date', $day)->pluck('id');
                AttendanceRecord::whereIn('circle_session_id', $sessions)->delete();
                CircleSession::whereIn('id', $sessions)->forceDelete();
            }
            if (in_array($what, ['both', 'memorization'], true)) {
                MemorizationRecord::where('circle_id', $circle->id)->whereDate('recorded_on', $day)->forceDelete();
            }
            if (isset($row[3])) {
                CircleDayOff::updateOrCreate(['circle_id' => $circle->id, 'off_on' => $day], ['reason' => $row[3], 'created_by' => $teacher->id]);
            }
        }
    }

    private function pastStudyDay(Circle $circle, int $nth): string
    {
        $day = now()->subDay()->startOfDay();
        for ($found = 0; ; $day->subDay()) {
            if ($circle->meetsOn($day->dayOfWeek) && ++$found === $nth) {
                return $day->toDateString();
            }
        }
    }
}
