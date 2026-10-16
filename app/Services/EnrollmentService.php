<?php

namespace App\Services;

use App\Models\Circle;
use App\Models\CircleEnrollment;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves students between circles while keeping the full membership history.
 * Historical attendance, memorization and assessment rows are never touched.
 */
class EnrollmentService
{
    public function assign(Student $student, ?Circle $circle, string $reason = null, bool $overrideCapacity = false): void
    {
        if ($circle && $student->current_circle_id === $circle->id) {
            return;
        }

        if ($circle) {
            if ($circle->status !== 'active') {
                throw ValidationException::withMessages(['circle_id' => 'لا يمكن التسجيل في حلقة متوقفة.']);
            }
            if ($circle->isFull() && ! $overrideCapacity) {
                throw ValidationException::withMessages([
                    'circle_id' => "الحلقة «{$circle->name}» ممتلئة ({$circle->capacity} طالب). فعّل خيار تجاوز السعة إن كان لديك الصلاحية.",
                ]);
            }
        }

        DB::transaction(function () use ($student, $circle, $reason, $overrideCapacity) {
            $from = $student->circle?->name;
            $today = now()->toDateString();

            CircleEnrollment::where('student_id', $student->id)
                ->whereNull('left_at')
                ->update(['left_at' => $today, 'reason' => $reason]);

            if ($circle) {
                CircleEnrollment::create([
                    'student_id' => $student->id,
                    'circle_id' => $circle->id,
                    'joined_at' => $today,
                    'reason' => $reason,
                    'created_by' => auth()->id(),
                ]);
            }

            $student->current_circle_id = $circle?->id;
            $student->save();

            AuditLogger::log(
                'student.transfer',
                "نقل الطالب {$student->full_name} من «".($from ?? 'بدون حلقة').'» إلى «'.($circle?->name ?? 'بدون حلقة').'»',
                $student,
                ['circle' => $from],
                ['circle' => $circle?->name, 'capacity_override' => $overrideCapacity, 'reason' => $reason],
            );
        });
    }
}
