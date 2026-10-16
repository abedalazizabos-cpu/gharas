<?php

namespace App\Http\Controllers\Visitor;

use App\Http\Controllers\Admin\ReportController as AdminReportController;

/**
 * Same center/circle/teacher reports the admin sees, for external
 * delegations and supporters. Read-only, and students appear by first
 * name and initial only (no student number), because these accounts
 * belong to people outside the center.
 */
class ReportController extends AdminReportController
{
    protected function presentRows($rows)
    {
        return $rows->each(function ($row) {
            $row['student']->setAttribute('full_name', $row['student']->maskedName());
            $row['student']->setAttribute('student_number', null);
        });
    }
}
