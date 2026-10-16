<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Circle;
use App\Models\CircleSession;
use App\Models\MemorizationRecord;
use App\Models\Setting;
use App\Models\Student;
use App\Models\WeeklyArchive;
use App\Support\PdfExport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Weeks run Sunday to Saturday. Each finished week is saved as one PDF file per circle
 * under storage/app/private/archives/{year}/{month}/, so past months stay browsable and downloadable.
 */
class WeeklyArchiveService
{
    public const DAYS = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

    public static function weekStart(Carbon|string|null $date = null): Carbon
    {
        $d = $date instanceof Carbon ? $date->copy() : ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? Carbon::parse($date) : now());

        return $d->startOfDay()->startOfWeek(Carbon::SUNDAY);
    }

    public static function label(Carbon $start): string
    {
        return 'من '.$start->format('Y/m/d').' إلى '.$start->copy()->addDays(6)->format('Y/m/d');
    }

    /** Records and attendance of one circle (or one student) in a week. */
    public function week(Carbon $start, ?Circle $circle = null, ?Student $student = null, bool $parentView = false): array
    {
        $end = $start->copy()->addDays(6);
        $records = MemorizationRecord::query()
            ->with(['student', 'surah', 'teacher'])
            ->whereBetween('recorded_on', [$start->toDateString(), $end->toDateString()])
            ->when($circle, fn ($q) => $q->where('circle_id', $circle->id))
            ->when($student, fn ($q) => $q->where('student_id', $student->id))
            ->when($parentView, fn ($q) => $q->where('shared_with_parent', true))
            ->orderBy('recorded_on')->orderBy('id')->get();

        $attendance = AttendanceRecord::query()
            ->with(['student', 'session'])
            ->whereHas('session', fn ($q) => $q->whereBetween('session_date', [$start->toDateString(), $end->toDateString()])
                ->when($circle, fn ($q2) => $q2->where('circle_id', $circle->id)))
            ->when($student, fn ($q) => $q->where('student_id', $student->id))
            ->get();

        $days = collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i));
        // student id => [Y-m-d => status]
        $grid = $attendance->groupBy('student_id')->map(fn (Collection $rows) => $rows->mapWithKeys(fn ($a) => [$a->session->session_date->toDateString() => $a]));
        $students = $attendance->pluck('student')->merge($records->pluck('student'))->filter()->unique('id')->sortBy('full_name')->values();
        $sessionDays = $attendance->map(fn ($a) => $a->session->session_date->toDateString())->unique()->values();

        return compact('start', 'end', 'records', 'attendance', 'days', 'grid', 'students', 'sessionDays');
    }

    /** For visitors/supporters: names shortened and teacher notes left out, as in their reports. */
    public function mask(array $w): array
    {
        $w['students']->merge($w['records']->pluck('student'))->merge($w['attendance']->pluck('student'))->filter()
            ->each(fn ($s) => $s->setAttribute('full_name', $s->maskedName()));
        $w['records']->each(fn ($r) => $r->setAttribute('notes', null)->setAttribute('recommendations', null));
        $w['attendance']->each(fn ($a) => $a->setAttribute('notes', null));

        return $w;
    }

    /** PDF of a week built on the fly with masked names (never stored). */
    public function maskedPdf(Circle $circle, Carbon $start): string
    {
        return $this->pdf($circle, $this->mask($this->week($start, $circle)), false);
    }

    public function archive(Circle $circle, Carbon $start): WeeklyArchive
    {
        $w = $this->week($start, $circle);
        $path = 'archives/'.$start->format('Y').'/'.$start->format('m').'/week-'.$start->toDateString().'-circle-'.$circle->id.'.pdf';
        Storage::disk('local')->put($path, $this->pdf($circle, $w));

        return WeeklyArchive::updateOrCreate(
            ['circle_id' => $circle->id, 'week_start' => $start->toDateString()],
            ['path' => $path, 'records_count' => $w['records']->count(), 'attendance_count' => $w['attendance']->count(), 'generated_at' => now()],
        );
    }

    /**
     * Saves every finished week that has activity and no file yet. Runs from the scheduler and,
     * so it works without one (e.g. on Windows), whenever the archive page is opened.
     */
    public function archiveMissing(): int
    {
        $thisWeek = self::weekStart();
        $first = collect([
            MemorizationRecord::min('recorded_on'),
            CircleSession::whereHas('attendance')->min('session_date'),
        ])->filter()->min();
        if (! $first) {
            return 0;
        }
        $done = WeeklyArchive::get(['circle_id', 'week_start'])->map(fn ($a) => $a->circle_id.'|'.$a->week_start->toDateString())->flip();
        $active = $this->activeWeeks();
        $count = 0;
        foreach ($active as $key => [$circleId, $week]) {
            if ($week >= $thisWeek->toDateString() || isset($done[$key])) {
                continue;
            }
            $circle = Circle::withTrashed()->find($circleId);
            if ($circle) {
                $this->archive($circle, Carbon::parse($week));
                $count++;
            }
        }

        return $count;
    }

    /** "circleId|weekStart" => [circleId, weekStart] for every week with records or attendance. */
    private function activeWeeks(): array
    {
        $out = [];
        $add = function ($circleId, $date) use (&$out) {
            $w = self::weekStart(Carbon::parse($date))->toDateString();
            $out[$circleId.'|'.$w] = [$circleId, $w];
        };
        MemorizationRecord::query()->select('circle_id', 'recorded_on')->whereNotNull('circle_id')->distinct()->get()
            ->each(fn ($r) => $add($r->circle_id, $r->recorded_on));
        CircleSession::query()->whereHas('attendance')->get(['circle_id', 'session_date'])
            ->each(fn ($s) => $add($s->circle_id, $s->session_date));

        return $out;
    }

    private function pdf(Circle $circle, array $w, bool $withNotes = true): string
    {
        return PdfExport::render(...$this->document($circle, $w, $withNotes));
    }

    /** Title, subtitle and table sections of a week's file. */
    public function document(Circle $circle, array $w, bool $withNotes = true): array
    {
        $attendance = $w['students']->map(fn ($st) => array_merge([$st->full_name], $w['days']
            ->map(fn ($d) => isset($w['grid'][$st->id][$d->toDateString()]) ? $w['grid'][$st->id][$d->toDateString()]->statusLabel() : '')->all()));
        $records = $w['records']->map(fn ($r) => array_merge(
            [$r->recorded_on->format('Y/m/d'), $r->student?->full_name, $r->typeLabel(), $r->rangeLabel(), $r->score, $r->gradeLabel(), $r->mistakes_count],
            $withNotes ? [$r->notes, $r->shared_with_parent ? 'نعم' : 'لا'] : []));

        return [
            'السجل الأسبوعي: '.$circle->name,
            'المحفظ: '.($circle->teacher?->name ?? '—').'   ·   الأسبوع '.self::label($w['start']),
            [
                ['title' => 'الحضور والغياب', 'headers' => array_merge(['الطالب'], $w['days']->map(fn ($d) => self::DAYS[$d->dayOfWeek].' '.$d->format('m/d'))->all()),
                    'rows' => $attendance, 'empty' => 'لم يُسجَّل حضور في هذا الأسبوع.'],
                ['title' => 'سجلات الحفظ والمراجعة', 'headers' => array_merge(['التاريخ', 'الطالب', 'النوع', 'السورة والآيات', 'الدرجة', 'التقدير', 'الأخطاء'], $withNotes ? ['ملاحظات المحفظ', 'لولي الأمر'] : []),
                    'rows' => $records, 'text_cols' => [1, 3, 7], 'empty' => 'لا توجد سجلات في هذا الأسبوع.'],
            ],
        ];
    }
}
