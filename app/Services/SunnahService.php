<?php

namespace App\Services;

use App\Models\MemorizationRecord;
use App\Models\Student;
use App\Models\SunnahCourse;
use App\Models\SunnahEnrollment;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** قسم السنة: participants of a course and their results. */
class SunnahService
{
    /**
     * Makes the course's participants exactly $studentIds. Students who already have a result are
     * never removed here. Returns [added ids, removed ids].
     *
     * @param  array<int, int|string>  $studentIds
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    public function syncStudents(SunnahCourse $course, array $studentIds, bool $notify = true): array
    {
        $wanted = Student::whereIn('id', array_map('intval', $studentIds))->pluck('id');
        $current = SunnahEnrollment::where('sunnah_course_id', $course->id)->get(['id', 'student_id', 'status']);

        $remove = $current->whereNotIn('student_id', $wanted)->where('status', 'enrolled');
        $add = $wanted->diff($current->pluck('student_id'))->values();

        DB::transaction(function () use ($course, $remove, $add) {
            SunnahEnrollment::whereIn('id', $remove->pluck('id'))->delete();
            foreach ($add as $id) {
                SunnahEnrollment::create(['sunnah_course_id' => $course->id, 'student_id' => $id, 'status' => 'enrolled']);
            }
        });

        $added = $add->all();
        $removed = $remove->pluck('student_id')->values()->all();
        if ($added || $removed) {
            AuditLogger::log('sunnah.students', "تعديل طلاب دورة «{$course->title}»", $course, $removed ? ['removed' => $removed] : [], $added ? ['added' => $added] : []);
        }
        if ($notify && $added) {
            $this->notifyEnrolled($course, $added);
        }

        return [$added, $removed];
    }

    /**
     * Records the sheet of results. A row is graded once every rule has a score; rows left empty stay
     * «لم يُقيَّم بعد». Returns how many rows were graded.
     *
     * @param  array<int, array{scores?: array<int, mixed>, notes?: ?string}>  $rows enrollment id => input
     */
    public function gradeSheet(SunnahCourse $course, array $rows, string $evaluatedOn, User $by): int
    {
        $criteria = $course->criteria()->get();
        $max = (int) $criteria->sum('max_score');
        $enrollments = SunnahEnrollment::where('sunnah_course_id', $course->id)->whereIn('id', array_keys($rows))->with(['student.parents', 'scores'])->get();
        $graded = 0;
        $newResults = collect();

        DB::transaction(function () use ($course, $criteria, $max, $enrollments, $rows, $evaluatedOn, $by, &$graded, $newResults) {
            foreach ($enrollments as $e) {
                $input = $rows[$e->id] ?? [];
                $scores = (array) ($input['scores'] ?? []);
                $filled = $criteria->filter(fn ($c) => isset($scores[$c->id]) && $scores[$c->id] !== '' && $scores[$c->id] !== null);
                $notes = isset($input['notes']) && trim((string) $input['notes']) !== '' ? trim((string) $input['notes']) : null;

                if ($criteria->isEmpty() || $filled->count() < $criteria->count()) {
                    // Not fully graded: keep any note, leave the result as it was.
                    if ($notes !== $e->notes && ($notes !== null || $e->notes !== null)) {
                        $e->update(['notes' => $notes]);
                    }

                    continue;
                }

                $total = 0;
                $e->scores()->delete();
                foreach ($criteria as $c) {
                    $score = max(0, min($c->max_score, (int) $scores[$c->id]));
                    $e->scores()->create(['sunnah_criterion_id' => $c->id, 'max_score' => $c->max_score, 'score' => $score]);
                    $total += $score;
                }
                $percentage = $max ? round($total / $max * 100, 2) : 0;
                $wasGraded = $e->isGraded();
                $e->fill([
                    'percentage' => $percentage,
                    'grade' => MemorizationRecord::gradeForScore((int) floor($percentage)),
                    'status' => $percentage >= $course->pass_score ? 'passed' : 'failed',
                    'notes' => $notes,
                    'evaluated_on' => $evaluatedOn,
                    'evaluated_by' => $by->id,
                ])->save();
                $graded++;
                if (! $wasGraded) {
                    $newResults->push($e);
                }
            }
        });

        if ($graded) {
            AuditLogger::log('sunnah.grade', "رصد درجات {$graded} ".($graded > 2 && $graded < 11 ? 'طلاب' : 'طالب')." في دورة «{$course->title}»", $course);
        }
        if ($course->results_to_parents) {
            foreach ($newResults as $e) {
                foreach ($e->student->parents as $parent) {
                    $parent->notify(new SystemNotification('نتيجة دورة في قسم السنة', "رُصدت نتيجة {$e->student->full_name} في دورة «{$course->title}»: {$e->statusLabel()} بتقدير {$e->gradeLabel()}.", route('parent.children.show', $e->student), 'journal-bookmark'));
                }
            }
        }

        return $graded;
    }

    /** Course summary numbers: participants, graded, passed, average percentage. */
    public function summary(SunnahCourse $course): array
    {
        $rows = $course->relationLoaded('enrollments') ? $course->enrollments : $course->enrollments()->get();
        $graded = $rows->filter->isGraded();

        return [
            'students' => $rows->count(),
            'graded' => $graded->count(),
            'passed' => $graded->where('status', 'passed')->count(),
            'average' => $graded->isEmpty() ? null : round($graded->avg('percentage'), 1),
            'pass_rate' => $graded->isEmpty() ? null : (int) round($graded->where('status', 'passed')->count() / $graded->count() * 100),
        ];
    }

    /** The courses a student takes part in, newest first. Hidden results stay hidden for parents. */
    public function forStudent(Student $student): Collection
    {
        return $student->sunnahEnrollments()->whereHas('course')
            ->with(['course.teacher', 'course.criteria', 'scores'])
            ->get()->sortByDesc(fn ($e) => $e->course->starts_on?->toDateString() ?? $e->course->created_at?->toDateString())->values();
    }

    /** @param array<int, int> $studentIds */
    private function notifyEnrolled(SunnahCourse $course, array $studentIds): void
    {
        $teacher = $course->teacher?->name;
        foreach (Student::whereIn('id', $studentIds)->with('parents')->get() as $student) {
            foreach ($student->parents as $parent) {
                $parent->notify(new SystemNotification('دورة جديدة في قسم السنة', "سُجّل {$student->full_name} في دورة «{$course->title}»".($teacher ? " مع {$teacher}" : '').'.', route('parent.children.show', $student), 'journal-bookmark'));
            }
        }
    }
}
