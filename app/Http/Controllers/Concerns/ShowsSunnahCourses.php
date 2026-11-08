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

        $posts = $course->posts()->with(['follows'])->get();
        $rows = \App\Models\SunnahAttendance::whereHas('session', fn ($q) => $q->where('sunnah_course_id', $course->id))->get(['student_id', 'status'])->groupBy('student_id');
        $attendance = $rows->map(function ($r) {
            $c = $r->countBy('status');

            return ['total' => $r->count(), 'absent' => $c['absent'] ?? 0, 'rate' => (int) round((($c['present'] ?? 0) + ($c['late'] ?? 0)) / max(1, $r->count()) * 100)];
        });

        return view('shared.sunnah.show', $extra + [
            'course' => $course,
            'posts' => $posts,
            'attendance' => $attendance,
            'sessionsCount' => $course->sessions()->count(),
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

    protected function savePost(Request $request, SunnahCourse $course): void
    {
        $data = $request->validate([
            'type' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Models\SunnahPost::TYPES))],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:3000'],
            'due_on' => ['nullable', 'date'],
            'max_score' => ['nullable', 'integer', 'between:1,100'],
        ], [], ['type' => 'النوع', 'title' => 'العنوان', 'body' => 'التفاصيل', 'due_on' => 'تاريخ التسليم', 'max_score' => 'درجة الواجب']);
        if ($data['type'] !== 'homework') {
            $data['due_on'] = null;
            $data['max_score'] = null;
        } else {
            $data['max_score'] = $data['max_score'] ?? 10;
        }
        app(SunnahService::class)->addPost($course, $request->user(), $data);
    }

    protected function removePost(SunnahCourse $course, \App\Models\SunnahPost $post): void
    {
        abort_unless($post->sunnah_course_id === $course->id, 404);
        $post->delete();
        \App\Services\AuditLogger::log('sunnah.post.delete', "حذف {$post->typeLabel()} «{$post->title}» من دورة «{$course->title}»", $post);
    }

    protected function attendanceView(Request $request, SunnahCourse $course, string $area)
    {
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();
        $date = min($date, now()->toDateString());
        $course = $this->loadCourse($course);
        $session = \App\Models\SunnahSession::where('sunnah_course_id', $course->id)->whereDate('held_on', $date)->first();

        return view('shared.sunnah.attendance', [
            'course' => $course, 'area' => $area, 'date' => $date, 'session' => $session,
            'existing' => $session ? $session->attendance()->get()->keyBy('student_id') : collect(),
            'sessions' => $course->sessions()->withCount(['attendance as present_count' => fn ($q) => $q->whereIn('status', ['present', 'late'])])->get(),
        ]);
    }

    protected function saveAttendance(Request $request, SunnahCourse $course): string
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'topic' => ['nullable', 'string', 'max:200'],
            'attendance' => ['required', 'array'],
            'attendance.*.status' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Models\AttendanceRecord::STATUSES))],
            'attendance.*.note' => ['nullable', 'string', 'max:255'],
        ], [], ['date' => 'التاريخ', 'topic' => 'موضوع اللقاء']);
        $date = \Illuminate\Support\Carbon::parse($data['date'])->toDateString();
        app(SunnahService::class)->recordAttendance($course, $date, $data['topic'] ?? null, $data['attendance'], $request->user());

        return $date;
    }

    protected function homeworkView(SunnahCourse $course, \App\Models\SunnahPost $post, string $area)
    {
        abort_unless($post->sunnah_course_id === $course->id && $post->isHomework(), 404);

        return view('shared.sunnah.homework', [
            'course' => $this->loadCourse($course), 'post' => $post, 'area' => $area,
            'follows' => $post->follows()->get()->keyBy('student_id'),
        ]);
    }

    protected function saveHomework(Request $request, SunnahCourse $course, \App\Models\SunnahPost $post): int
    {
        abort_unless($post->sunnah_course_id === $course->id && $post->isHomework(), 404);
        $data = $request->validate([
            'rows' => ['nullable', 'array'],
            'rows.*.score' => ['nullable', 'integer', 'between:0,'.($post->max_score ?: 10)],
            'rows.*.feedback' => ['nullable', 'string', 'max:255'],
        ], [], ['rows.*.score' => 'الدرجة', 'rows.*.feedback' => 'الملاحظة']);

        return app(SunnahService::class)->gradeHomework($post, $data['rows'] ?? [], $request->user());
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
