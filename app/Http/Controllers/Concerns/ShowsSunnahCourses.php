<?php

namespace App\Http\Controllers\Concerns;

use App\Models\SunnahCourse;
use App\Services\SunnahService;
use App\Support\PdfExport;
use Illuminate\Http\Request;

/** What the admin and the course's sheikh share: the course page, the results sheet and its PDF. */
trait ShowsSunnahCourses
{
    protected function loadCourse(SunnahCourse $course): SunnahCourse
    {
        return $course->load(['teacher', 'criteria', 'enrollments' => fn ($q) => $q->with(['student.circle', 'scores'])
            ->join('students', 'students.id', '=', 'sunnah_enrollments.student_id')->orderBy('students.full_name')->select('sunnah_enrollments.*')]);
    }

    protected function courseView(SunnahCourse $course, array $extra = [])
    {
        $course = $this->loadCourse($course);

        return view('shared.sunnah.show', $extra + [
            'course' => $course,
            'summary' => app(SunnahService::class)->summary($course),
        ]);
    }

    protected function gradeView(SunnahCourse $course, string $action, string $back)
    {
        return view('shared.sunnah.grade', ['course' => $this->loadCourse($course), 'action' => $action, 'back' => $back]);
    }

    protected function saveGrades(Request $request, SunnahCourse $course): int
    {
        $course->load('criteria');
        $rules = ['evaluated_on' => ['required', 'date', 'before_or_equal:today'], 'rows' => ['nullable', 'array'], 'rows.*.notes' => ['nullable', 'string', 'max:1000']];
        foreach ($course->criteria as $c) {
            $rules['rows.*.scores.'.$c->id] = ['nullable', 'integer', 'between:0,'.$c->max_score];
        }
        $data = $request->validate($rules, [], ['evaluated_on' => 'تاريخ التقييم', 'rows.*.scores.*' => 'الدرجة', 'rows.*.notes' => 'الملاحظة']);

        return app(SunnahService::class)->gradeSheet($course, $data['rows'] ?? [], $data['evaluated_on'], $request->user());
    }

    /** Arabic PDF with the center logo: course details and every participant's result. */
    protected function coursePdf(SunnahCourse $course)
    {
        $course = $this->loadCourse($course);
        $criteria = $course->criteria;
        $s = app(SunnahService::class)->summary($course);

        $info = [
            ['المجال', $course->categoryLabel()], ['الشيخ', $course->teacher?->name ?? '—'], ['المدة', $course->periodLabel()],
            ['الموعد', $course->schedule ?: '—'], ['الحالة', $course->statusLabel()], ['درجة النجاح', $course->pass_score.'%'],
            ['المشاركون', $s['students']], ['قُيّم منهم', $s['graded']], ['الناجحون', $s['passed']], ['متوسط النتيجة', $s['average'] !== null ? $s['average'].'%' : '—'],
        ];
        $sections = [['title' => 'بيانات الدورة', 'headers' => ['البيان', 'القيمة'], 'rows' => $info, 'text_cols' => [0, 1]]];
        if ($objectives = $course->objectiveList()) {
            $sections[] = ['title' => 'أهداف الدورة', 'headers' => ['#', 'الهدف'], 'rows' => collect($objectives)->map(fn ($o, $i) => [$i + 1, $o])->all(), 'text_cols' => [1]];
        }
        $sections[] = [
            'title' => 'نتائج الطلاب',
            'headers' => array_merge(['الطالب', 'الحلقة'], $criteria->map(fn ($c) => $c->name.' ('.$c->max_score.')')->all(), ['النسبة', 'التقدير', 'النتيجة']),
            'rows' => $course->enrollments->map(function ($e) use ($criteria) {
                $scores = $e->scores->keyBy('sunnah_criterion_id');

                return array_merge(
                    [$e->student->full_name, $e->student->circle?->name ?? '—'],
                    $criteria->map(fn ($c) => isset($scores[$c->id]) ? $scores[$c->id]->score : '—')->all(),
                    [$e->percentLabel(), $e->gradeLabel() ?? '—', $e->statusLabel()],
                );
            })->all(),
            'text_cols' => [0, 1],
        ];

        return PdfExport::download('دورة-'.$course->title.'.pdf', 'دورة «'.$course->title.'» - قسم السنة', $course->categoryLabel().' · '.$course->periodLabel(), $sections, $criteria->count() > 3);
    }
}
