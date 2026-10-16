<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Circle;
use App\Models\CircleSession;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class AttendanceService
{
    /**
     * Saves attendance for a circle on a date. A session row is unique per
     * circle/day and a record is unique per session/student, so re-saving the
     * same day updates the existing records instead of duplicating them.
     *
     * @param  array<int, array{status: string, notes?: string|null}>  $entries  keyed by student id
     */
    public function record(Circle $circle, string $date, array $entries, User $by, ?string $sessionNotes = null): CircleSession
    {
        return DB::transaction(function () use ($circle, $date, $entries, $by, $sessionNotes) {
            $session = CircleSession::firstOrCreate(
                ['circle_id' => $circle->id, 'session_date' => $date],
                ['teacher_id' => $circle->teacher_id, 'start_time' => $circle->start_time],
            );
            if ($sessionNotes !== null) {
                $session->update(['notes' => $sessionNotes]);
            }

            $allowed = $circle->students()->pluck('id')->all();
            $newlyAbsent = [];
            $changed = [];

            foreach ($entries as $studentId => $entry) {
                if (! in_array((int) $studentId, $allowed, true)) {
                    continue;
                }
                $record = AttendanceRecord::firstOrNew([
                    'circle_session_id' => $session->id,
                    'student_id' => $studentId,
                ]);
                $previous = $record->exists ? $record->status : null;
                $record->status = $entry['status'];
                $record->notes = $entry['notes'] ?? null;
                $record->recorded_by = $by->id;
                $record->save();

                if ($previous !== null && $previous !== $record->status) {
                    $changed[$studentId] = [$previous, $record->status];
                }
                if ($record->status === 'absent' && $previous !== 'absent') {
                    $newlyAbsent[] = $record;
                }
            }

            if ($changed !== []) {
                AuditLogger::log('attendance.update', "تعديل حضور حلقة {$circle->name} بتاريخ {$date}", $session, [], ['changes' => $changed]);
            }

            foreach ($newlyAbsent as $record) {
                $student = $record->student;
                Notification::send($student->parents, new SystemNotification(
                    'تنبيه غياب',
                    "تم تسجيل غياب {$student->full_name} عن حلقة {$circle->name} بتاريخ {$date}.",
                    route('parent.children.show', $student),
                    'calendar-x',
                ));
            }

            return $session;
        });
    }
}
