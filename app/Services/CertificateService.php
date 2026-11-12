<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\QualityTest;
use App\Models\Setting;
use App\Models\Student;
use App\Models\SunnahCourse;
use App\Models\SunnahEnrollment;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Support\Hijri;
use App\Support\Juz;
use App\Support\PdfExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Certificates are never issued automatically: the admin picks passed results from the eligible list
 * and issues them. The wording comes from the admin's templates and is frozen into each certificate.
 */
class CertificateService
{
    /**
     * Passed results that have no certificate yet.
     *
     * @return Collection<int, array{key: string, kind: string, student: Student, subject: string, grade: ?string, percent: ?string, date: ?string}>
     */
    public function eligible(?string $kind = null, ?string $search = null): Collection
    {
        $items = collect();
        $searchStudent = fn ($q) => $search ? $q->whereHas('student', fn ($s) => $s->where('full_name', 'like', "%{$search}%")->orWhere('student_number', 'like', "%{$search}%")) : $q;

        if (! $kind || $kind === 'course') {
            $enrollments = $searchStudent(SunnahEnrollment::where('status', 'passed')->whereDoesntHave('certificate')
                ->whereHas('course')->whereHas('student')->with(['course', 'student.circle']))->get();
            foreach ($enrollments as $e) {
                $items->push(['key' => 'course:'.$e->id, 'kind' => 'course', 'student' => $e->student, 'subject' => 'دورة «'.$e->course->title.'»',
                    'grade' => $e->gradeLabel(), 'percent' => $e->percentLabel(), 'date' => $e->evaluated_on?->toDateString()]);
            }
        }
        foreach (['individual', 'combined'] as $k) {
            if ($kind && $kind !== $k) {
                continue;
            }
            $tests = $searchStudent(QualityTest::passed()->where('kind', $k)->whereDoesntHave('certificate')->whereHas('student')->with(['student.circle', 'stage']))->get();
            foreach ($tests as $t) {
                $items->push(['key' => 'test:'.$t->id, 'kind' => $k, 'student' => $t->student, 'subject' => $t->subjectLabel(),
                    'grade' => $t->gradeLabel(), 'percent' => $t->percentage !== null ? rtrim(rtrim(number_format($t->percentage, 2), '0'), '.').'%' : null,
                    'date' => $t->tested_on?->toDateString()]);
            }
        }

        return $items->sortByDesc('date')->values();
    }

    /**
     * Issues a certificate for each picked result ("course:ID" or "test:ID"). Results that already have one are skipped.
     *
     * @param  array<int, string>  $keys
     */
    public function issue(array $keys, User $by, ?string $issuedOn = null, bool $notify = true): Collection
    {
        $issuedOn ??= now()->toDateString();
        $issued = collect();

        DB::transaction(function () use ($keys, $by, $issuedOn, $issued) {
            $templates = CertificateTemplate::all()->keyBy('kind');
            foreach (array_unique($keys) as $key) {
                [$type, $id] = array_pad(explode(':', (string) $key, 2), 2, null);
                $source = match ($type) {
                    'course' => SunnahEnrollment::where('status', 'passed')->whereDoesntHave('certificate')->with(['course.teacher', 'student.circle'])->find($id),
                    'test' => QualityTest::passed()->whereDoesntHave('certificate')->with(['student.circle', 'stage', 'examiner'])->find($id),
                    default => null,
                };
                if (! $source || ! $source->student || ($type === 'course' && ! $source->course)) {
                    continue;
                }
                $kind = $type === 'course' ? 'course' : $source->kind;
                $template = $templates[$kind] ?? null;
                if (! $template) {
                    continue;
                }
                $number = $this->nextNumber($issuedOn);
                [$values, $subject] = $this->values($source, $number, $issuedOn);

                $issued->push(Certificate::create([
                    'number' => $number,
                    'student_id' => $source->student->id,
                    'kind' => $kind,
                    'sunnah_enrollment_id' => $type === 'course' ? $source->id : null,
                    'quality_test_id' => $type === 'test' ? $source->id : null,
                    'issued_on' => $issuedOn,
                    'issued_by' => $by->id,
                    'content' => $template->render($values) + [
                        'student' => $source->student->full_name, 'subject' => $subject, 'center' => $values['{المركز}'],
                        'number' => $number, 'date' => $values['{التاريخ}'],
                        'hijri' => Hijri::enabled() ? $values['{التاريخ_الهجري}'] : null,
                    ],
                ]));
            }
        });

        if ($issued->isNotEmpty()) {
            AuditLogger::log('certificate.issue', 'إصدار '.$issued->count().' '.($issued->count() > 2 && $issued->count() < 11 ? 'شهادات' : 'شهادة').': '.$issued->take(5)->map(fn ($c) => $c->content['student'])->implode('، ').($issued->count() > 5 ? ' …' : ''));
            if ($notify) {
                foreach ($issued as $c) {
                    app(SmsService::class)->toParents($c->student, "مبارك! صدرت {$c->kindLabel()} لـ{$c->content['student']}: {$c->subject()}. يمكنكم تحميلها من حسابكم في الموقع.", 'certificate');
                    foreach ($c->student->parents ?? [] as $parent) {
                        $parent->notify(new SystemNotification('شهادة جديدة', "صدرت {$c->kindLabel()} لـ{$c->content['student']}: {$c->subject()}. يمكنك تحميلها PDF.", route('parent.children.show', [$c->student_id, 'tab' => 'certificates']).'#certificates', 'award'));
                    }
                }
            }
        }

        return $issued;
    }

    /** Placeholder values for one result, and the one-line subject. */
    public function values(SunnahEnrollment|QualityTest $source, string $number, string $issuedOn): array
    {
        $student = $source->student;
        $female = $student->gender === 'female';
        $values = [
            '{الطالب}' => $student->full_name,
            '{اللقب}' => $female ? 'الطالبة' : 'الطالب',
            '{له}' => $female ? 'لها' : 'له',
            '{ه}' => $female ? 'ها' : 'ه',
            '{ت}' => $female ? 'ت' : '',
            '{المركز}' => Setting::get('center_name', 'مركز غراس لتحفيظ القرآن الكريم'),
            '{الحلقة}' => $student->circle?->name ?? '',
            '{التاريخ}' => str_replace('-', '/', $issuedOn),
            '{التاريخ_الهجري}' => Hijri::format($issuedOn),
            '{رقم_الشهادة}' => $number,
            '{الدورة}' => '', '{الشيخ}' => '', '{الجزء}' => '', '{الأجزاء}' => '', '{المختبر}' => '',
        ];

        if ($source instanceof SunnahEnrollment) {
            $values['{الدورة}'] = $source->course->title;
            $values['{الشيخ}'] = $source->course->teacher ? $source->course->teacher->name : '';
            $values['{التقدير}'] = (string) $source->gradeLabel();
            $values['{النسبة}'] = (string) $source->percentLabel();
            $subject = 'دورة «'.$source->course->title.'»';
        } else {
            $values['{الجزء}'] = $source->isIndividual() ? Juz::label((int) $source->juz) : '';
            $values['{الأجزاء}'] = $source->isIndividual() ? '' : Juz::rangeLabel($source->from_juz, $source->to_juz);
            $values['{المختبر}'] = $source->examiner ? $source->examiner->name : '';
            $values['{التقدير}'] = (string) $source->gradeLabel();
            $values['{النسبة}'] = $source->percentage !== null ? rtrim(rtrim(number_format($source->percentage, 2), '0'), '.').'%' : '';
            $subject = $source->subjectLabel();
        }

        return [$values, $subject];
    }

    /** Sample values to preview a template before issuing anything. */
    public function sampleValues(): array
    {
        return [
            '{الطالب}' => 'عبدالله محمد أحمد', '{اللقب}' => 'الطالب', '{له}' => 'له', '{ه}' => 'ه', '{ت}' => '',
            '{المركز}' => Setting::get('center_name', 'مركز غراس لتحفيظ القرآن الكريم'), '{الحلقة}' => 'حلقة أبي بكر الصديق',
            '{التقدير}' => 'ممتاز', '{النسبة}' => '95%', '{التاريخ}' => now()->format('Y/m/d'), '{التاريخ_الهجري}' => Hijri::format(now()), '{رقم_الشهادة}' => 'GH-'.now()->year.'-0000',
            '{الدورة}' => 'آداب حملة القرآن', '{الشيخ}' => 'الشيخ أحمد علي', '{الجزء}' => Juz::label(30),
            '{الأجزاء}' => Juz::rangeLabel(30, 28), '{المختبر}' => 'الشيخ خالد سعيد',
        ];
    }

    public function pdf(Certificate $certificate): string
    {
        return PdfExport::certificate($certificate->content);
    }

    public function previewPdf(CertificateTemplate $template): string
    {
        $values = $this->sampleValues();

        return PdfExport::certificate($template->render($values) + [
            'student' => $values['{الطالب}'], 'center' => $values['{المركز}'], 'number' => $values['{رقم_الشهادة}'], 'date' => $values['{التاريخ}'], 'preview' => true,
            'hijri' => Hijri::enabled() ? $values['{التاريخ_الهجري}'] : null,
        ]);
    }

    /** Admin, the parent, the teacher of the student's circle, the course sheikh or the test examiner. */
    public function canView(User $user, Certificate $certificate): bool
    {
        $student = $certificate->student;
        if (! $student) {
            return false;
        }

        return match (true) {
            $user->isAdmin() => $user->hasPermission('certificates.manage') || $user->hasPermission('students.manage'),
            $user->isParent() => $user->isParentOf($student),
            $user->isTeacher() => $user->teachesStudent($student)
                || ($certificate->enrollment && $certificate->enrollment->course?->teacher_id === $user->id)
                || ($certificate->qualityTest && $certificate->qualityTest->examiner_id === $user->id),
            default => false,
        };
    }

    /** Certificates of the teacher's circle students and of the students in the courses he gives. */
    public function forTeacher(User $teacher): Builder
    {
        return Certificate::query()->where(function ($q) use ($teacher) {
            $q->whereIn('student_id', $teacher->teacherStudents()->select('id'))
                ->orWhereIn('sunnah_enrollment_id', SunnahEnrollment::whereIn('sunnah_course_id', SunnahCourse::where('teacher_id', $teacher->id)->select('id'))->select('id'));
        });
    }

    /** GH-2026-0001 … Numbers of withdrawn certificates are never given again. */
    private function nextNumber(string $issuedOn): string
    {
        $year = substr($issuedOn, 0, 4);
        $prefix = 'GH-'.$year.'-';
        $last = Certificate::where('number', 'like', $prefix.'%')->orderByDesc('number')->value('number');
        $n = max($last ? (int) substr($last, strlen($prefix)) : 0, (int) Setting::get('certificate_last_'.$year, '0')) + 1;
        Setting::put('certificate_last_'.$year, (string) $n);

        return $prefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    public static function filename(Certificate $c): string
    {
        return 'شهادة-'.$c->number.'-'.$c->content['student'].'.pdf';
    }
}
