<?php

namespace App\Http\Controllers\Visitor;

use App\Http\Controllers\Controller;
use App\Models\SunnahCourse;
use App\Services\SunnahService;
use App\Support\PdfExport;
use Illuminate\Http\Request;

/** Read-only overview of قسم السنة for delegations and supporters: numbers only, no student names. */
class SunnahController extends Controller
{
    public function index(Request $request, SunnahService $sunnah)
    {
        SunnahCourse::syncStatuses();
        $courses = SunnahCourse::with(['teacher', 'enrollments'])->orderByRaw("CASE status WHEN 'running' THEN 0 WHEN 'planned' THEN 1 ELSE 2 END")->latest('starts_on')->get();

        $rows = $courses->map(fn ($c) => ['course' => $c, 'summary' => $sunnah->summary($c)]);

        if (in_array($request->query('export'), ['pdf', 'xlsx'], true)) {
            abort_unless($request->user()->hasPermission('visitor.download'), 403);

            return PdfExport::download('دورات-قسم-السنة.pdf', 'دورات قسم السنة', 'أعداد ونسب فقط، دون أسماء الطلاب', [[
                'title' => '', 'empty' => 'لا توجد دورات.',
                'headers' => ['الدورة', 'المجال', 'الشيخ', 'المدة', 'الحالة', 'المشاركون', 'قُيّم', 'نسبة النجاح %', 'متوسط النتيجة %'],
                'rows' => $rows->map(fn ($r) => [$r['course']->title, $r['course']->categoryLabel(), $r['course']->teacher?->name ?? '—', $r['course']->periodLabel(), $r['course']->statusLabel(),
                    $r['summary']['students'], $r['summary']['graded'], $r['summary']['pass_rate'] ?? '—', $r['summary']['average'] ?? '—']),
                'text_cols' => [0, 1, 2, 3],
            ]]);
        }

        return view('visitor.sunnah', ['rows' => $rows]);
    }
}
