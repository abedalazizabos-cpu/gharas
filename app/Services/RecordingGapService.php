<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Circle;
use App\Models\CircleDayOff;
use App\Models\CircleSession;
use App\Models\MemorizationRecord;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Finds study days on which a teacher did not record a circle's attendance or recitation.
 * A day counts only once it has ended, only on the circle's own days (never Friday), and not on
 * a day marked as off (holiday, the teacher was ill …). When attendance was taken and every
 * student was absent, no recitation is expected that day.
 */
class RecordingGapService
{
    /** How many days back the dashboards look. */
    public const RECENT_DAYS = 7;

    /**
     * @param  Collection<int, Circle>|null  $circles  active circles to check (default: all active circles with a teacher)
     * @return Collection<int, array{circle: Circle, teacher: ?User, date: Carbon, attendance: bool, memorization: bool}>
     *                                                  attendance/memorization = true when that part is missing
     */
    public function gaps(?Collection $circles = null, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $yesterday = now()->subDay()->startOfDay();
        $to = ($to ?? $yesterday)->copy()->startOfDay()->min($yesterday);
        $from = ($from ?? $to->copy()->subDays(self::RECENT_DAYS - 1))->copy()->startOfDay();
        if ($from->gt($to)) {
            return collect();
        }

        $circles = ($circles ?? Circle::active()->whereNotNull('teacher_id')->get())
            ->filter(fn (Circle $c) => $c->status === 'active' && $c->teacher_id)
            ->loadMissing('teacher')
            ->loadCount(['students' => fn ($q) => $q->active()])
            ->filter(fn (Circle $c) => $c->students_count > 0)
            ->values();
        if ($circles->isEmpty()) {
            return collect();
        }
        $ids = $circles->pluck('id');
        [$a, $b] = [$from->toDateString(), $to->toDateString()];

        // A circle is checked from its first recorded meeting (or from when it was created).
        $firstDay = CircleSession::whereIn('circle_id', $ids)->groupBy('circle_id')->selectRaw('circle_id, min(session_date) as first_day')->pluck('first_day', 'circle_id');
        $taken = CircleSession::whereIn('circle_id', $ids)->whereBetween('session_date', [$a, $b])->has('attendance')
            ->get(['circle_id', 'session_date'])->map(fn ($s) => $s->circle_id.'|'.$s->session_date->toDateString())->flip();
        $someoneCame = AttendanceRecord::join('circle_sessions', 'circle_sessions.id', '=', 'attendance_records.circle_session_id')
            ->whereNull('circle_sessions.deleted_at')->whereIn('circle_sessions.circle_id', $ids)->whereBetween('circle_sessions.session_date', [$a, $b])
            ->whereIn('attendance_records.status', ['present', 'late'])
            ->distinct()->get(['circle_sessions.circle_id', 'circle_sessions.session_date'])
            ->map(fn ($r) => $r->circle_id.'|'.Carbon::parse($r->session_date)->toDateString())->flip();
        $recited = MemorizationRecord::whereIn('circle_id', $ids)->whereBetween('recorded_on', [$a, $b])
            ->distinct()->get(['circle_id', 'recorded_on'])->map(fn ($r) => $r->circle_id.'|'.Carbon::parse($r->recorded_on)->toDateString())->flip();
        $offs = CircleDayOff::whereBetween('off_on', [$a, $b])->where(fn ($q) => $q->whereNull('circle_id')->orWhereIn('circle_id', $ids))
            ->get()->map(fn ($o) => ($o->circle_id ?? '*').'|'.$o->off_on->toDateString())->flip();

        $gaps = collect();
        foreach ($circles as $circle) {
            $start = Carbon::parse($firstDay[$circle->id] ?? $circle->created_at ?? $from)->startOfDay()->max($from);
            for ($day = $start->copy(); $day->lte($to); $day->addDay()) {
                if (! in_array($day->dayOfWeek, Circle::STUDY_DAYS, true) || ! $circle->meetsOn($day->dayOfWeek)) {
                    continue;
                }
                $key = $circle->id.'|'.$day->toDateString();
                if (isset($offs[$key]) || isset($offs['*|'.$day->toDateString()])) {
                    continue;
                }
                $noAttendance = ! isset($taken[$key]);
                $noRecitation = ! isset($recited[$key]) && ($noAttendance || isset($someoneCame[$key]));
                if ($noAttendance || $noRecitation) {
                    $gaps->push(['circle' => $circle, 'teacher' => $circle->teacher, 'date' => $day->copy(), 'attendance' => $noAttendance, 'memorization' => $noRecitation]);
                }
            }
        }

        return $gaps->sortByDesc(fn ($g) => $g['date']->toDateString().'|'.$g['circle']->name)->values();
    }

    public function forTeacher(User $teacher, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        return $this->gaps($teacher->circles()->active()->get(), $from, $to);
    }

    public static function missingLabel(array $gap): string
    {
        return match (true) {
            $gap['attendance'] && $gap['memorization'] => 'الحضور والتسميع',
            $gap['attendance'] => 'الحضور والغياب',
            default => 'التسميع',
        };
    }

    /**
     * The daily alert (run each morning by the scheduler): each teacher is notified of what is missing
     * from yesterday, and the admins get one summary. Runs once per day even if called again.
     */
    public function notifyYesterday(): int
    {
        $day = now()->subDay()->startOfDay();
        if (Setting::get('recording_alerts_last') === $day->toDateString()) {
            return 0;
        }
        Setting::put('recording_alerts_last', $day->toDateString());

        $gaps = $this->gaps(null, $day, $day);
        foreach ($gaps->groupBy(fn ($g) => $g['teacher']?->id) as $rows) {
            $teacher = $rows->first()['teacher'];
            if (! $teacher || $teacher->status !== 'active') {
                continue;
            }
            $lines = $rows->map(fn ($g) => '«'.$g['circle']->name.'»: '.self::missingLabel($g))->implode('، ');
            $teacher->notify(new SystemNotification('لم يُسجَّل يوم أمس', 'لم تسجّل '.$lines.' بتاريخ '.$day->format('Y/m/d').'. سجّلها الآن، أو بيّن أن الحلقة لم تنعقد.', route('teacher.dashboard').'#recording-gaps', 'exclamation-octagon'));
        }

        if ($gaps->isNotEmpty()) {
            $teachers = $gaps->pluck('teacher.name')->filter()->unique();
            $admins = User::admins()->where('status', 'active')->get()->filter(fn (User $u) => $u->hasPermission('attendance.manage'));
            foreach ($admins as $admin) {
                $admin->notify(new SystemNotification('محفظون لم يسجّلوا يوم أمس', $teachers->count().' '.($teachers->count() > 2 && $teachers->count() < 11 ? 'محفظين' : 'محفظ').' لم يسجّلوا الحضور أو التسميع بتاريخ '.$day->format('Y/m/d').': '.$teachers->take(6)->implode('، ').($teachers->count() > 6 ? ' …' : ''), route('admin.recording-gaps.index'), 'exclamation-octagon'));
            }
        }

        return $gaps->count();
    }
}
