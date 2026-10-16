<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssessmentRequest;
use App\Models\Assessment;
use App\Models\AssessmentCriterion;
use App\Models\Student;
use App\Notifications\SystemNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class AssessmentController extends Controller
{
    public function index(Request $request)
    {
        $teacher = $request->user();

        return view('teacher.assessments.index', [
            'assessments' => Assessment::query()
                ->where(fn ($q) => $q->where('teacher_id', $teacher->id)->orWhereIn('student_id', $teacher->teacherStudents()->select('id')))
                ->with(['student', 'scores.criterion'])
                ->latest('assessed_on')->paginate(20),
        ]);
    }

    public function create(Request $request)
    {
        return view('teacher.assessments.create', [
            'students' => $request->user()->teacherStudents()->active()->orderBy('full_name')->get(),
            'criteria' => AssessmentCriterion::active()->get(),
            'selected' => $request->integer('student') ?: null,
        ]);
    }

    public function store(AssessmentRequest $request)
    {
        $student = Student::findOrFail($request->student_id);
        $criteria = AssessmentCriterion::active()->get();
        abort_if($criteria->isEmpty(), 422, 'لا توجد معايير تقييم مفعلة.');

        $assessment = DB::transaction(function () use ($request, $student, $criteria) {
            $total = 0;
            $max = 0;
            $assessment = new Assessment([
                'assessed_on' => $request->assessed_on,
                'notes' => $request->notes,
                'shared_with_parent' => $request->boolean('shared_with_parent'),
                'percentage' => 0,
            ]);
            $assessment->student_id = $student->id;
            $assessment->circle_id = $student->current_circle_id;
            $assessment->teacher_id = $request->user()->id;
            $assessment->save();

            foreach ($criteria as $criterion) {
                $score = (int) $request->input("scores.{$criterion->id}");
                $assessment->scores()->create([
                    'assessment_criterion_id' => $criterion->id,
                    'max_score' => $criterion->max_score,
                    'score' => $score,
                ]);
                $total += $score;
                $max += $criterion->max_score;
            }
            $assessment->update(['percentage' => round($total / $max * 100, 2)]);

            return $assessment;
        });

        if ($assessment->shared_with_parent) {
            Notification::send($student->parents, new SystemNotification(
                'تقييم جديد', "تقييم {$student->full_name}: {$assessment->percentage}% ({$assessment->gradeLabel()})",
                route('parent.children.show', [$student, 'tab' => 'assessments']), 'award'
            ));
        }

        return redirect()->route('teacher.students.show', $student)->with('success', 'تم حفظ التقييم بنجاح.');
    }
}
