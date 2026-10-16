<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Circle;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.assessments.index', [
            'assessments' => Assessment::query()
                ->with(['student', 'circle', 'teacher', 'scores.criterion'])
                ->when($request->query('q'), fn ($q, $t) => $q->whereHas('student', fn ($s) => $s->search($t)))
                ->when($request->query('circle'), fn ($q, $c) => $q->where('circle_id', $c))
                ->latest('assessed_on')->paginate(20)->withQueryString(),
            'circles' => Circle::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function destroy(Assessment $assessment)
    {
        $this->authorize('delete', $assessment);
        $assessment->delete();
        AuditLogger::log('assessment.delete', "حذف تقييم للطالب {$assessment->student->full_name}", $assessment, ['percentage' => $assessment->percentage]);

        return back()->with('success', 'تم حذف التقييم.');
    }
}
