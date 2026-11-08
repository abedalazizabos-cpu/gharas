<?php

namespace App\Http\Controllers\Visitor;

use App\Http\Controllers\Controller;
use App\Models\SunnahCourse;
use App\Services\SunnahService;

/** Read-only overview of قسم السنة for delegations and supporters: numbers only, no student names. */
class SunnahController extends Controller
{
    public function index(SunnahService $sunnah)
    {
        $courses = SunnahCourse::with(['teacher', 'enrollments'])->orderByRaw("CASE status WHEN 'running' THEN 0 WHEN 'planned' THEN 1 ELSE 2 END")->latest('starts_on')->get();

        return view('visitor.sunnah', [
            'rows' => $courses->map(fn ($c) => ['course' => $c, 'summary' => $sunnah->summary($c)]),
        ]);
    }
}
