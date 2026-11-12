<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\MonthlyReportService;
use App\Support\PdfExport;
use Illuminate\Http\Request;

/** The student's monthly report PDF, for the admin, the circle's teacher and the student's parents. */
class MonthlyReportController extends Controller
{
    public function download(Request $request, Student $student, MonthlyReportService $reports)
    {
        $user = $request->user();
        abort_unless(match (true) {
            $user->isAdmin() => $user->hasPermission('students.manage') || $user->hasPermission('reports.view'),
            $user->isTeacher() => $user->teachesStudent($student),
            $user->isParent() => $user->isParentOf($student),
            default => false,
        }, 403);

        $month = $request->query('month');
        if (! MonthlyReportService::validMonth($month)) {
            $month = now()->startOfMonth()->subMonth()->format('Y-m');
        }

        return PdfExport::response($reports->pdf($student, $month), MonthlyReportService::filename($student, $month));
    }
}
