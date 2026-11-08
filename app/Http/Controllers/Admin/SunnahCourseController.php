<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ShowsSunnahCourses;
use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\Student;
use App\Models\SunnahCourse;
use App\Models\SunnahScore;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SunnahService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** قسم السنة: the admin creates each course, its objectives, sheikh, students and grading rules. */
class SunnahCourseController extends Controller
{
    use ShowsSunnahCourses;

    public function __construct(private SunnahService $sunnah) {}

    public function index(Request $request)
    {
        $courses = SunnahCourse::with('teacher')->withCount(['enrollments', 'enrollments as graded_count' => fn ($q) => $q->graded()])
            ->when($request->query('q'), fn ($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->when($request->query('category'), fn ($q, $v) => $q->where('category', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('teacher'), fn ($q, $v) => $q->where('teacher_id', $v))
            ->orderByRaw("CASE status WHEN 'running' THEN 0 WHEN 'planned' THEN 1 ELSE 2 END")->latest('starts_on')->latest('id')
            ->paginate(15)->withQueryString();

        return view('admin.sunnah.index', [
            'courses' => $courses,
            'teachers' => User::role(User::ROLE_TEACHER)->orderBy('name')->get(['id', 'name']),
            'counts' => SunnahCourse::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function create(Request $request)
    {
        $category = array_key_exists((string) $request->query('category'), SunnahCourse::CATEGORIES) ? $request->query('category') : 'hadith';
        $course = new SunnahCourse(['category' => $category, 'status' => 'planned', 'pass_score' => 70, 'results_to_parents' => true, 'starts_on' => now()->toDateString()]);
        $criteria = collect(SunnahCourse::defaultCriteria($category))->map(fn ($c) => ['id' => null, 'name' => $c[0], 'max_score' => $c[1]]);

        return view('admin.sunnah.form', $this->formData($course, $criteria));
    }

    public function store(Request $request)
    {
        [$data, $criteria, $studentIds] = $this->validated($request);

        $course = DB::transaction(function () use ($data, $criteria, $request) {
            $course = new SunnahCourse($data);
            $course->created_by = $request->user()->id;
            $course->save();
            foreach (array_values($criteria) as $i => $c) {
                $course->criteria()->create(['name' => $c['name'], 'max_score' => $c['max_score'], 'sort_order' => $i + 1]);
            }

            return $course;
        });
        AuditLogger::log('sunnah.create', "إنشاء دورة «{$course->title}» في قسم السنة", $course, [], $course->only(['title', 'category', 'teacher_id', 'starts_on', 'ends_on']));
        $this->sunnah->syncStudents($course, $studentIds);
        $this->notifyTeacher($course);

        return redirect()->route('admin.sunnah.show', $course)->with('success', 'تم إنشاء الدورة.');
    }

    public function show(Request $request, SunnahCourse $course)
    {
        if ($request->query('export') === 'pdf') {
            return $this->coursePdf($course);
        }

        return $this->courseView($course, ['area' => 'admin']);
    }

    public function edit(SunnahCourse $course)
    {
        $course->load('criteria');

        return view('admin.sunnah.form', $this->formData($course, $course->criteria->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'max_score' => $c->max_score])));
    }

    public function update(Request $request, SunnahCourse $course)
    {
        [$data, $criteria, $studentIds] = $this->validated($request, $course);
        $oldTeacher = $course->teacher_id;

        DB::transaction(function () use ($course, $data, $criteria) {
            $course->fill($data)->save();
            AuditLogger::logChanges('sunnah.update', "تعديل دورة «{$course->title}»", $course);

            $keep = collect($criteria)->pluck('id')->filter()->map(fn ($id) => (int) $id);
            $course->criteria()->whereNotIn('id', $keep)->delete();
            foreach (array_values($criteria) as $i => $c) {
                $attrs = ['name' => $c['name'], 'max_score' => $c['max_score'], 'sort_order' => $i + 1];
                $c['id'] ? $course->criteria()->whereKey($c['id'])->update($attrs) : $course->criteria()->create($attrs);
            }
        });
        $this->sunnah->syncStudents($course, $studentIds);
        if ($course->teacher_id && $course->teacher_id !== $oldTeacher) {
            $this->notifyTeacher($course);
        }

        return redirect()->route('admin.sunnah.show', $course)->with('success', 'تم حفظ الدورة.');
    }

    public function destroy(SunnahCourse $course)
    {
        $course->delete();
        AuditLogger::log('sunnah.delete', "حذف دورة «{$course->title}» من قسم السنة", $course);

        return redirect()->route('admin.sunnah.index')->with('success', 'تم حذف الدورة.');
    }

    public function gradeForm(SunnahCourse $course)
    {
        return $this->gradeView($course, route('admin.sunnah.grade.store', $course), route('admin.sunnah.show', $course));
    }

    public function grade(Request $request, SunnahCourse $course)
    {
        $n = $this->saveGrades($request, $course);

        return redirect()->route('admin.sunnah.show', $course)->with('success', $n ? "تم رصد درجات {$n} من الطلاب." : 'حُفظت الملاحظات. لا يُرصد الطالب إلا إذا أُدخلت كل درجاته.');
    }

    // ----------------------------------------------------------------

    private function formData(SunnahCourse $course, $criteria): array
    {
        $enrolled = $course->exists ? $course->enrollments()->get(['student_id', 'status']) : collect();

        return [
            'course' => $course,
            'criteria' => collect(old('criteria', $criteria->all()))->values(),
            'teachers' => User::role(User::ROLE_TEACHER)->active()->orderBy('name')->get(['id', 'name']),
            'students' => Student::active()->with('circle:id,name')->orderBy('full_name')->get(['id', 'full_name', 'student_number', 'gender', 'current_circle_id']),
            'circles' => Circle::active()->orderBy('name')->get(['id', 'name']),
            'selected' => collect(old('student_ids', $enrolled->pluck('student_id')->all()))->map(fn ($id) => (int) $id)->all(),
            'locked' => $enrolled->where('status', '!=', 'enrolled')->pluck('student_id')->all(),
            'usedCriteria' => $course->exists ? SunnahScore::whereIn('sunnah_criterion_id', $course->criteria()->pluck('id'))->distinct()->pluck('sunnah_criterion_id')->all() : [],
        ];
    }

    /** @return array{0: array, 1: array<int, array{id: ?int, name: string, max_score: int}>, 2: array<int, int>} */
    private function validated(Request $request, ?SunnahCourse $course = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(SunnahCourse::CATEGORIES))],
            'description' => ['nullable', 'string', 'max:3000'],
            'objectives' => ['nullable', 'string', 'max:3000'],
            'teacher_id' => ['required', Rule::exists('users', 'id')->where('role', User::ROLE_TEACHER)->whereNull('deleted_at')],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'schedule' => ['nullable', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(array_keys(SunnahCourse::STATUSES))],
            'pass_score' => ['required', 'integer', 'between:1,100'],
            'criteria' => ['required', 'array', 'min:1', 'max:12'],
            'criteria.*.id' => ['nullable', 'integer'],
            'criteria.*.name' => ['required', 'string', 'max:100'],
            'criteria.*.max_score' => ['required', 'integer', 'between:1,100'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', Rule::exists('students', 'id')->whereNull('deleted_at')],
        ], [
            'teacher_id.required' => 'اختر الشيخ الذي يعطي الدورة.',
            'criteria.required' => 'أضف ضابط تقييم واحداً على الأقل.',
        ], [
            'title' => 'اسم الدورة', 'category' => 'المجال', 'objectives' => 'الأهداف', 'teacher_id' => 'الشيخ',
            'starts_on' => 'تاريخ البداية', 'ends_on' => 'تاريخ النهاية', 'pass_score' => 'درجة النجاح',
            'criteria.*.name' => 'اسم الضابط', 'criteria.*.max_score' => 'الدرجة العظمى',
        ]);

        $criteria = collect($data['criteria'])->map(fn ($c) => ['id' => isset($c['id']) ? (int) $c['id'] : null, 'name' => trim($c['name']), 'max_score' => (int) $c['max_score']])->values();
        if ($course) {
            $own = $course->criteria()->pluck('id');
            if ($criteria->pluck('id')->filter()->diff($own)->isNotEmpty()) {
                throw ValidationException::withMessages(['criteria' => 'ضوابط التقييم لا تخص هذه الدورة.']);
            }
            $removedUsed = SunnahScore::whereIn('sunnah_criterion_id', $own->diff($criteria->pluck('id')->filter()))->exists();
            if ($removedUsed) {
                throw ValidationException::withMessages(['criteria' => 'لا يمكن حذف ضابط رُصدت عليه درجات. يمكنك تعديل اسمه أو درجته.']);
            }
        }

        $studentIds = array_map('intval', $data['student_ids'] ?? []);
        if ($course) {
            // Students who already have a result always stay in the course.
            $studentIds = array_values(array_unique(array_merge($studentIds, $course->enrollments()->graded()->pluck('student_id')->all())));
        }
        $data['results_to_parents'] = $request->boolean('results_to_parents');
        unset($data['criteria'], $data['student_ids']);

        return [$data, $criteria->all(), $studentIds];
    }

    private function notifyTeacher(SunnahCourse $course): void
    {
        $course->teacher?->notify(new \App\Notifications\SystemNotification('دورة جديدة في قسم السنة', "كُلّفت بإعطاء دورة «{$course->title}» ({$course->categoryLabel()}).", route('teacher.sunnah.show', $course), 'journal-bookmark'));
    }
}
