<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Certificate;
use App\Models\Circle;
use App\Models\HonorEntry;
use App\Models\MemorizationRecord;
use App\Models\QualityTest;
use App\Models\Setting;
use App\Models\Student;
use App\Notifications\SystemNotification;
use App\Support\Hijri;
use App\Support\PdfExport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The monthly report of a student (PDF): attendance, recitation in pages, tests and certificates of
 * one month. Each report is built when it is downloaded; on the 1st of each month the parents are
 * told that last month's report is ready.
 */
class MonthlyReportService
{
    public function __construct(private MonthlyStats $stats, private ProgressService $progress) {}

    /** Months a report can be asked for: from the student's enrollment up to this month, newest first. */
    public function months(Student $student, int $limit = 12): Collection
    {
        $start = Carbon::parse($student->enrollment_date ?? $student->created_at ?? now())->startOfMonth();
        $months = collect();
        for ($m = now()->startOfMonth(); $m->gte($start) && $months->count() < $limit; $m->subMonth()) {
            $months->push($m->format('Y-m'));
        }

        return $months->whenEmpty(fn ($c) => $c->push(now()->format('Y-m')));
    }

    public static function validMonth(?string $month): bool
    {
        return is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) && $month <= now()->format('Y-m');
    }

    public function pdf(Student $student, string $month): string
    {
        [$from, $to] = MonthlyStats::range($month);
        $student->loadMissing('circle.teacher');
        $st = $this->stats->forStudents([$student->id], $month)[$student->id];
        $label = HonorEntry::monthLabel($month);
        $honor = HonorEntry::where('month', $month)->where('student_id', $student->id)->with('circle')->first();
        $range = [$from->toDateString(), $to->toDateString()];

        $summary = [
            ['الحلقة', $student->circle?->name ?? '—'],
            ['المحفظ', $student->circle?->teacher?->name ?? '—'],
            ['أيام الحلقة المسجلة', (string) $st['days']],
            ['حاضر / متأخر', $st['present'].' / '.$st['late']],
            ['غائب / غائب بعذر', $st['absent'].' / '.$st['excused']],
            ['نسبة الحضور', $st['attendance'] !== null ? $st['attendance'].'%' : '—'],
            ['الحفظ الجديد المجتاز', MemorizationRecord::formatPages($st['new_pages'])],
            ['المراجعة والتثبيت', MemorizationRecord::formatPages($st['revision_pages'])],
            ['مرات التسميع', (string) $st['records']],
            ['متوسط درجة التسميع', $st['average'] !== null ? $st['average'].' ('.MemorizationRecord::GRADES[MemorizationRecord::gradeForScore((int) round($st['average']))].')' : '—'],
            ['إجمالي المحفوظ من المصحف', $this->progress->quranPercent($student).'%'],
        ];
        if ($honor) {
            $summary[] = ['لوحة الشرف', 'الأول في «'.$honor->circle?->name.'» لهذا الشهر'];
        }

        // Parents see only the records the teacher shared, so the report shows the same.
        $records = $student->memorizationRecords()->where('shared_with_parent', true)->whereBetween('recorded_on', $range)
            ->with('surah')->orderBy('recorded_on')->orderBy('id')->get()
            ->map(fn (MemorizationRecord $r) => [$r->recorded_on->format('Y/m/d'), $r->typeLabel(), $r->rangeLabel(), $r->pagesLabel(), $r->score, $r->gradeLabel(), (string) $r->notes]);
        $absences = AttendanceRecord::where('student_id', $student->id)->whereIn('status', ['absent', 'excused', 'late'])
            ->whereHas('session', fn ($q) => $q->whereBetween('session_date', $range))->with('session')->get()
            ->sortBy(fn ($a) => $a->session->session_date)
            ->map(fn (AttendanceRecord $a) => [Circle::DAYS[$a->session->session_date->dayOfWeek].' '.$a->session->session_date->format('Y/m/d'), $a->statusLabel()]);
        $tests = QualityTest::where('student_id', $student->id)->whereIn('status', ['passed', 'failed'])->whereBetween('tested_on', $range)->orderBy('tested_on')->get()
            ->map(fn (QualityTest $t) => [$t->tested_on->format('Y/m/d'), $t->subjectLabel(), $t->statusLabel(), (string) $t->gradeLabel()]);
        $certificates = Certificate::where('student_id', $student->id)->whereBetween('issued_on', $range)->orderBy('issued_on')->get()
            ->map(fn (Certificate $c) => [$c->issued_on->format('Y/m/d'), $c->kindLabel(), $c->subject()]);

        $hijri = Hijri::enabled() ? ' ('.Hijri::format($from).' - '.Hijri::format($to).')' : '';

        return PdfExport::render('التقرير الشهري للطالب', $student->full_name.' · شهر '.$label.$hijri, [
            ['title' => 'ملخص الشهر', 'headers' => ['البند', 'النتيجة'], 'rows' => $summary, 'text_cols' => [0, 1]],
            ['title' => 'سجل التسميع', 'headers' => ['التاريخ', 'النوع', 'السورة', 'المقدار', 'الدرجة', 'التقدير', 'ملاحظات المحفظ'], 'rows' => $records, 'text_cols' => [2, 6], 'empty' => 'لا توجد سجلات تسميع في هذا الشهر.'],
            ['title' => 'أيام الغياب والتأخر', 'headers' => ['اليوم', 'الحالة'], 'rows' => $absences, 'text_cols' => [0], 'empty' => 'لم يغب الطالب ولم يتأخر في هذا الشهر.'],
            ['title' => 'اختبارات لجنة الجودة', 'headers' => ['التاريخ', 'الاختبار', 'النتيجة', 'التقدير'], 'rows' => $tests, 'text_cols' => [1], 'empty' => 'لا توجد اختبارات في هذا الشهر.'],
            ['title' => 'الشهادات', 'headers' => ['التاريخ', 'الشهادة', 'الموضوع'], 'rows' => $certificates, 'text_cols' => [1, 2], 'empty' => 'لا توجد شهادات في هذا الشهر.'],
        ], landscape: false);
    }

    public static function filename(Student $student, string $month): string
    {
        return 'التقرير-الشهري-'.$student->full_name.'-'.$month.'.pdf';
    }

    /**
     * On the 1st: tells each parent that last month's report of each child is ready (once per month).
     * With the automatic SMS switched on, each parent also gets one text message.
     */
    public function notifyParents(?string $month = null): int
    {
        $month ??= now()->startOfMonth()->subMonth()->format('Y-m');
        if (Setting::get('monthly_reports', '1') !== '1' || Setting::get('monthly_reports_last') === $month) {
            return 0;
        }
        Setting::put('monthly_reports_last', $month);
        $label = HonorEntry::monthLabel($month);
        $sms = app(SmsService::class);
        $count = 0;

        $students = Student::active()->whereHas('parents')->with('parents')->get();
        $byParent = [];
        foreach ($students as $student) {
            foreach ($student->parents as $parent) {
                if ($parent->status !== 'active') {
                    continue;
                }
                $parent->notify(new SystemNotification('التقرير الشهري', "تقرير {$student->full_name} عن شهر {$label} جاهز: الحضور والحفظ والاختبارات. اضغط لتحميله PDF.",
                    route('monthly-reports.download', [$student, 'month' => $month]), 'file-earmark-pdf'));
                $byParent[$parent->id]['user'] = $parent;
                $byParent[$parent->id]['names'][] = $student->full_name;
                $count++;
            }
        }
        if ($sms->autoEnabled('monthly')) {
            foreach ($byParent as $row) {
                $sms->send([$row['user']->phone], 'التقرير الشهري لـ'.implode(' و', $row['names'])." عن شهر {$label} جاهز في حسابكم على موقع المركز: ".route('login'), 'monthly');
            }
        }

        return $count;
    }
}
