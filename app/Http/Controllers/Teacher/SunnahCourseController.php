<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\ShowsSunnahCourses;
use App\Http\Controllers\Controller;
use App\Models\SunnahCourse;
use Illuminate\Http\Request;

/** The sheikh's side of قسم السنة: the courses the admin gave him, and their results. */
class SunnahCourseController extends Controller
{
    use ShowsSunnahCourses;

    public function index(Request $request)
    {
        SunnahCourse::syncStatuses();
        return view('teacher.sunnah.index', [
            'courses' => SunnahCourse::for($request->user())->withCount(['enrollments', 'enrollments as graded_count' => fn ($q) => $q->graded()])
                ->orderByRaw("CASE status WHEN 'running' THEN 0 WHEN 'planned' THEN 1 ELSE 2 END")->latest('starts_on')->get(),
        ]);
    }

    public function show(Request $request, SunnahCourse $course)
    {
        $this->authorizeTeacher($request, $course);
        if ($request->query('export') === 'pdf') {
            return $this->coursePdf($course);
        }

        return $this->courseView($course, ['area' => 'teacher']);
    }

    public function gradeForm(Request $request, SunnahCourse $course)
    {
        $this->authorizeTeacher($request, $course);

        return $this->gradeView($course, route('teacher.sunnah.grade.store', $course), route('teacher.sunnah.show', $course));
    }

    public function grade(Request $request, SunnahCourse $course)
    {
        $this->authorizeTeacher($request, $course);
        $n = $this->saveGrades($request, $course);

        return redirect()->route('teacher.sunnah.show', $course)->with('success', $n ? "تم رصد درجات {$n} من الطلاب." : 'حُفظت الملاحظات. لا يُرصد الطالب إلا إذا أُدخلت كل درجاته.');
    }

    public function attendance(Request $request, SunnahCourse $course)
    {
        $this->authorizeTeacher($request, $course);
        return $this->attendanceView($request, $course, 'teacher');
    }

    public function storeAttendance(Request $request, SunnahCourse $course)
    {
        $this->authorizeTeacher($request, $course);
        $date = $this->saveAttendance($request, $course);

        return redirect()->route('teacher.sunnah.attendance', [$course, 'date' => $date])->with('success', 'تم حفظ حضور اللقاء.');
    }

    public function homework(Request $request, SunnahCourse $course, \App\Models\SunnahPost $post)
    {
        $this->authorizeTeacher($request, $course);
        return $this->homeworkView($course, $post, 'teacher');
    }

    public function storeHomework(Request $request, SunnahCourse $course, \App\Models\SunnahPost $post)
    {
        $this->authorizeTeacher($request, $course);
        $n = $this->saveHomework($request, $course, $post);

        return redirect()->to(route('teacher.sunnah.show', $course).'#posts')->with('success', "تم حفظ تقييم الواجب ({$n} طالب).");
    }

    public function storePost(Request $request, SunnahCourse $course)
    {
        $this->authorizeTeacher($request, $course);
        $this->savePost($request, $course);

        return redirect()->to(route('teacher.sunnah.show', $course).'#posts')->with('success', 'تم النشر، ووصل إشعار لأولياء أمور الطلاب.');
    }

    public function destroyPost(Request $request, SunnahCourse $course, \App\Models\SunnahPost $post)
    {
        $this->authorizeTeacher($request, $course);
        $this->removePost($course, $post);

        return redirect()->to(route('teacher.sunnah.show', $course).'#posts')->with('success', 'تم الحذف.');
    }

    private function authorizeTeacher(Request $request, SunnahCourse $course): void
    {
        abort_unless($course->teacher_id === $request->user()->id, 403, 'هذه الدورة يعطيها شيخ آخر.');
    }
}
