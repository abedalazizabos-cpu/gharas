<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\TakesAttendance;
use App\Http\Controllers\Controller;

class AttendanceController extends Controller
{
    use TakesAttendance;

    protected function attendanceRoutePrefix(): string
    {
        return 'teacher.attendance';
    }
}
