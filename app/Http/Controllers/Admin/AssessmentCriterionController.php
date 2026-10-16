<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentCriterion;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class AssessmentCriterionController extends Controller
{
    public function index()
    {
        return view('admin.criteria.index', ['criteria' => AssessmentCriterion::orderBy('sort_order')->get()]);
    }

    public function store(Request $request)
    {
        $criterion = AssessmentCriterion::create($this->validated($request));
        AuditLogger::log('criterion.create', "إضافة معيار تقييم «{$criterion->name}»", $criterion, [], $criterion->only(['name', 'max_score']));

        return back()->with('success', 'تمت إضافة معيار التقييم.');
    }

    public function update(Request $request, AssessmentCriterion $criterion)
    {
        $criterion->update($this->validated($request));
        AuditLogger::logChanges('criterion.update', "تعديل معيار التقييم «{$criterion->name}»", $criterion);

        return back()->with('success', 'تم تحديث معيار التقييم.');
    }

    public function destroy(AssessmentCriterion $criterion)
    {
        if (\App\Models\AssessmentScore::where('assessment_criterion_id', $criterion->id)->exists()) {
            $criterion->update(['is_active' => false]);

            return back()->with('success', 'المعيار مستخدم في تقييمات سابقة، لذلك تم إيقافه بدلاً من حذفه.');
        }
        $criterion->delete();
        AuditLogger::log('criterion.delete', "حذف معيار التقييم «{$criterion->name}»", $criterion);

        return back()->with('success', 'تم حذف معيار التقييم.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'max_score' => ['required', 'integer', 'between:1,100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [], ['name' => 'اسم المعيار', 'max_score' => 'الدرجة العظمى']);

        return $data + ['is_active' => $request->boolean('is_active'), 'sort_order' => $data['sort_order'] ?? 0];
    }
}
