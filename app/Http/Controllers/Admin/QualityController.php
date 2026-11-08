<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CombinedTestStage;
use App\Models\QualityCriterion;
use App\Models\QualityTest;
use App\Models\QualityTestScore;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\QualityService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** لجنة الجودة: alerts, assigning tests to examiners, results, and the committee settings. */
class QualityController extends Controller
{
    public function __construct(private QualityService $quality) {}

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), ['alerts', 'combined', 'tests'], true) ? $request->query('tab') : 'alerts';
        $examiners = self::examiners();
        $data = ['tab' => $tab, 'examiners' => $examiners, 'passScore' => QualityService::passScore()];

        $students = Student::active()->with('circle.teacher')->orderBy('full_name')->get();
        $status = $this->quality->statusFor($students);
        $data['alertCount'] = $status->filter(fn ($s) => $s['missing_individual'] !== [])->count();
        $data['dueCount'] = $status->filter(fn ($s) => $s['due_combined'] !== [])->count();

        if ($tab === 'alerts') {
            $data['rows'] = $status->filter(fn ($s) => $s['missing_individual'] !== [])->values();
        } elseif ($tab === 'combined') {
            $data['rows'] = $status->filter(fn ($s) => $s['due_combined'] !== [])->values();
        } else {
            $data['tests'] = QualityTest::with(['student.circle', 'examiner', 'stage'])
                ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
                ->when($request->query('kind'), fn ($q, $v) => $q->where('kind', $v))
                ->when($request->query('examiner'), fn ($q, $v) => $q->where('examiner_id', $v))
                ->when($request->query('q'), fn ($q, $v) => $q->whereHas('student', fn ($s) => $s->search($v)))
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->latest('assigned_on')->latest('id')
                ->paginate(20)->withQueryString();
        }

        return view('admin.quality.index', $data);
    }

    public function assign(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'kind' => ['required', Rule::in(['individual', 'combined'])],
            'juz' => ['required_if:kind,individual', 'nullable', 'integer', 'between:1,30'],
            'stage_id' => ['required_if:kind,combined', 'nullable', Rule::exists('combined_test_stages', 'id')],
            'examiner_id' => ['required', Rule::exists('users', 'id')->where('role', User::ROLE_TEACHER)->where('is_quality_examiner', true)->whereNull('deleted_at')],
        ], ['examiner_id.exists' => 'اختر شيخاً من أعضاء لجنة الجودة.'], ['examiner_id' => 'شيخ الجودة', 'juz' => 'الجزء', 'stage_id' => 'الاختبار المجتمع']);

        $student = Student::findOrFail($data['student_id']);
        $stage = $data['kind'] === 'combined' ? CombinedTestStage::findOrFail($data['stage_id']) : null;
        $test = $this->quality->assign($student, User::findOrFail($data['examiner_id']), $request->user(), $stage ? null : (int) $data['juz'], $stage);

        return back()->with('success', "تم إرسال الطالب {$student->full_name} إلى {$test->examiner->name} ل{$test->kindLabel()}: {$test->subjectLabel()}.");
    }

    public function destroy(QualityTest $test)
    {
        $test->delete();
        AuditLogger::log('quality.cancel', "إلغاء {$test->kindLabel()} ({$test->subjectLabel()}) للطالب {$test->student->full_name}", $test);

        return back()->with('success', 'تم إلغاء الاختبار.');
    }

    public function gradeForm(QualityTest $test)
    {
        return view('shared.quality-grade', [
            'test' => $test->load(['student.circle', 'examiner', 'scores', 'stage']),
            'criteria' => QualityCriterion::active()->get(),
            'action' => route('admin.quality.grade.store', $test),
            'back' => route('admin.quality.index', ['tab' => 'tests']),
        ]);
    }

    public function grade(Request $request, QualityTest $test)
    {
        $this->quality->grade($test, ...self::gradeInput($request));

        return redirect()->route('admin.quality.index', ['tab' => 'tests'])->with('success', "تم رصد النتيجة: {$test->statusLabel()} ({$test->percentage}%).");
    }

    // ---------------------------------------------------------------- Settings of the committee

    public function settings()
    {
        return view('admin.quality.settings', [
            'teachers' => User::role(User::ROLE_TEACHER)->orderByDesc('is_quality_examiner')->orderBy('name')
                ->withCount(['circles' => fn ($q) => $q->active()])->get(),
            'criteria' => QualityCriterion::orderBy('sort_order')->orderBy('id')->get(),
            'stages' => CombinedTestStage::orderBy('sort_order')->orderBy('id')->get(),
            'passScore' => QualityService::passScore(),
        ]);
    }

    public function examiner(Request $request, User $teacher)
    {
        abort_unless($teacher->isTeacher(), 404);
        $teacher->is_quality_examiner = $request->boolean('on');
        $teacher->save();
        AuditLogger::log('quality.settings', ($teacher->is_quality_examiner ? 'إضافة' : 'إخراج')." {$teacher->name} ".($teacher->is_quality_examiner ? 'إلى' : 'من').' لجنة الجودة', $teacher);

        return back()->with('success', $teacher->is_quality_examiner ? "أصبح {$teacher->name} من لجنة الجودة." : "أُخرج {$teacher->name} من لجنة الجودة.");
    }

    public function passScore(Request $request)
    {
        $data = $request->validate(['quality_pass_score' => ['required', 'integer', 'between:1,100']], [], ['quality_pass_score' => 'درجة النجاح']);
        $old = QualityService::passScore();
        Setting::put('quality_pass_score', (string) $data['quality_pass_score']);
        AuditLogger::log('quality.settings', 'تعديل درجة النجاح في اختبارات الجودة', null, ['quality_pass_score' => $old], ['quality_pass_score' => $data['quality_pass_score']]);

        return back()->with('success', 'تم حفظ درجة النجاح.');
    }

    public function storeCriterion(Request $request)
    {
        $c = QualityCriterion::create($this->criterionInput($request));
        AuditLogger::log('quality.settings', "إضافة ضابط تقييم «{$c->name}»", $c, [], $c->only(['name', 'max_score']));

        return back()->with('success', 'تمت إضافة الضابط.');
    }

    public function updateCriterion(Request $request, QualityCriterion $criterion)
    {
        $criterion->update($this->criterionInput($request));
        AuditLogger::logChanges('quality.settings', "تعديل ضابط التقييم «{$criterion->name}»", $criterion);

        return back()->with('success', 'تم حفظ الضابط.');
    }

    public function destroyCriterion(QualityCriterion $criterion)
    {
        if (QualityTestScore::where('quality_criterion_id', $criterion->id)->exists()) {
            $criterion->update(['is_active' => false]);

            return back()->with('success', 'الضابط مستخدم في اختبارات سابقة، لذلك أُوقف بدلاً من حذفه.');
        }
        $criterion->delete();
        AuditLogger::log('quality.settings', "حذف ضابط التقييم «{$criterion->name}»");

        return back()->with('success', 'تم حذف الضابط.');
    }

    public function storeStage(Request $request)
    {
        $stage = CombinedTestStage::create($this->stageInput($request));
        AuditLogger::log('quality.settings', 'إضافة مرحلة اختبار مجتمع: '.$stage->label(), $stage);

        return back()->with('success', 'تمت إضافة المرحلة.');
    }

    public function updateStage(Request $request, CombinedTestStage $stage)
    {
        $stage->update($this->stageInput($request));
        AuditLogger::logChanges('quality.settings', 'تعديل مرحلة اختبار مجتمع: '.$stage->label(), $stage);

        return back()->with('success', 'تم حفظ المرحلة.');
    }

    public function destroyStage(CombinedTestStage $stage)
    {
        if (QualityTest::withTrashed()->where('stage_id', $stage->id)->exists()) {
            $stage->update(['is_active' => false]);

            return back()->with('success', 'المرحلة لها اختبارات سابقة، لذلك أُوقفت بدلاً من حذفها.');
        }
        $stage->delete();
        AuditLogger::log('quality.settings', 'حذف مرحلة اختبار مجتمع: '.$stage->label());

        return back()->with('success', 'تم حذف المرحلة.');
    }

    // ----------------------------------------------------------------

    public static function examiners()
    {
        return User::role(User::ROLE_TEACHER)->where('is_quality_examiner', true)->active()->orderBy('name')->get(['id', 'name']);
    }

    /** @return array{0: array, 1: ?string, 2: string} scores, notes, tested_on */
    public static function gradeInput(Request $request): array
    {
        $rules = ['notes' => ['nullable', 'string', 'max:2000'], 'tested_on' => ['required', 'date', 'before_or_equal:today']];
        foreach (QualityCriterion::active()->get() as $c) {
            $rules['scores.'.$c->id] = ['required', 'integer', 'between:0,'.$c->max_score];
        }
        $data = $request->validate($rules, [], ['tested_on' => 'تاريخ الاختبار', 'scores.*' => 'الدرجة']);

        return [$data['scores'] ?? [], $data['notes'] ?? null, $data['tested_on']];
    }

    private function criterionInput(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'max_score' => ['required', 'integer', 'between:1,100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [], ['name' => 'اسم الضابط', 'max_score' => 'الدرجة العظمى']);

        return $data + ['is_active' => $request->boolean('is_active'), 'sort_order' => $data['sort_order'] ?? 0];
    }

    private function stageInput(Request $request): array
    {
        $data = $request->validate([
            'from_juz' => ['required', 'integer', 'between:1,30'],
            'to_juz' => ['required', 'integer', 'between:1,30'],
            'name' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [], ['from_juz' => 'من الجزء', 'to_juz' => 'إلى الجزء']);

        return $data + ['is_active' => $request->boolean('is_active'), 'sort_order' => $data['sort_order'] ?? 0];
    }
}
