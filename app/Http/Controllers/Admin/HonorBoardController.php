<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\HonorEntry;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\HonorBoardService;
use Illuminate\Http\Request;

/** لوحة الشرف: the automatic monthly choice per circle, and replacing it by hand. */
class HonorBoardController extends Controller
{
    public function __construct(private HonorBoardService $honor) {}

    public function index(Request $request)
    {
        $current = HonorBoardService::currentMonth();
        $this->honor->ensure($current);
        $months = HonorEntry::select('month')->distinct()->orderByDesc('month')->pluck('month')->push($current)->unique()->sortDesc()->values();
        $month = in_array($request->query('month'), $months->all(), true) ? $request->query('month') : $current;

        $entries = $this->honor->board($month)->keyBy('circle_id');
        $circles = Circle::active()->with(['teacher', 'students' => fn ($q) => $q->active()])->orderBy('name')->get();

        return view('admin.honor.index', [
            'month' => $month,
            'current' => $current,
            'months' => $months,
            'rows' => $circles->map(fn (Circle $c) => [
                'circle' => $c,
                'entry' => $entries[$c->id] ?? null,
                'ranking' => $this->honor->ranking($c, $month)->take(8),
            ]),
        ]);
    }

    public function replace(Request $request, HonorEntry $entry)
    {
        $data = $request->validate(['student_id' => ['required', 'integer', 'exists:students,id']]);
        $this->honor->replace($entry, Student::findOrFail($data['student_id']), $request->user());

        return back()->with('success', 'تم تغيير الطالب في لوحة الشرف، وأُشعر وليّ أمره.');
    }

    /** Adds a circle that had nobody (e.g. no recitation recorded) by picking a student by hand. */
    public function store(Request $request)
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m'], 'student_id' => ['required', 'integer', 'exists:students,id']]);
        $student = Student::findOrFail($data['student_id']);
        abort_unless($student->current_circle_id && $student->status === 'active', 422);
        $entry = HonorEntry::firstOrNew(['month' => $data['month'], 'circle_id' => $student->current_circle_id]);
        if (! $entry->exists) {
            $entry->fill(['student_id' => $student->id, 'points' => 0])->save();
        }
        $this->honor->replace($entry, $student, $request->user());

        return back()->with('success', 'تمت إضافة الطالب إلى لوحة الشرف.');
    }

    /** Goes back to the automatic choice for every circle of the month. */
    public function rechoose(Request $request)
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        HonorEntry::where('month', $data['month'])->update(['picked_by' => null]);
        $n = $this->honor->choose($data['month']);
        AuditLogger::log('honor.rechoose', 'إعادة الاختيار التلقائي للوحة الشرف لشهر '.HonorEntry::monthLabel($data['month']));

        return back()->with('success', "أُعيد الاختيار التلقائي: {$n} طالباً في لوحة الشرف.");
    }
}
