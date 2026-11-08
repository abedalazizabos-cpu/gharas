<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Admin\QualityController as AdminQuality;
use App\Http\Controllers\Controller;
use App\Models\QualityCriterion;
use App\Models\QualityTest;
use App\Services\QualityService;
use Illuminate\Http\Request;

/** The examiner's side of the quality committee: the tests sent to them, and recording results. */
class QualityController extends Controller
{
    public function index(Request $request)
    {
        $mine = QualityTest::where('examiner_id', $request->user()->id)->with(['student.circle.teacher', 'stage']);

        return view('teacher.quality.index', [
            'pending' => (clone $mine)->pending()->orderBy('assigned_on')->get(),
            'done' => (clone $mine)->where('status', '!=', 'pending')->latest('tested_on')->latest('id')->paginate(15),
            'isExaminer' => $request->user()->isQualityExaminer(),
        ]);
    }

    public function gradeForm(Request $request, QualityTest $test)
    {
        $this->authorizeExaminer($request, $test);

        return view('shared.quality-grade', [
            'test' => $test->load(['student.circle', 'examiner', 'scores', 'stage']),
            'criteria' => QualityCriterion::active()->get(),
            'action' => route('teacher.quality.grade.store', $test),
            'back' => route('teacher.quality.index'),
        ]);
    }

    public function grade(Request $request, QualityTest $test, QualityService $quality)
    {
        $this->authorizeExaminer($request, $test);
        $quality->grade($test, ...AdminQuality::gradeInput($request));

        return redirect()->route('teacher.quality.index')->with('success', "تم رصد النتيجة: {$test->statusLabel()} ({$test->percentage}%).");
    }

    private function authorizeExaminer(Request $request, QualityTest $test): void
    {
        abort_unless($test->examiner_id === $request->user()->id, 403, 'هذا الاختبار مُسند لشيخ آخر.');
    }
}
