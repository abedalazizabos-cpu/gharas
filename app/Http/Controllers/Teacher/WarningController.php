<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\TeacherWarning;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class WarningController extends Controller
{
    /** Teacher confirms they have read a warning. */
    public function acknowledge(Request $request, TeacherWarning $warning)
    {
        abort_unless($warning->teacher_id === $request->user()->id, 403);
        if (! $warning->acknowledged_at) {
            $warning->update(['acknowledged_at' => now()]);
            AuditLogger::log('teacher.warn.ack', "اطلع المحفظ على {$warning->levelLabel()}", $warning);
        }

        return back()->with('success', 'شكراً، تم تسجيل اطلاعك.');
    }
}
