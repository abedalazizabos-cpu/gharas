<?php

namespace App\Http\Controllers\Concerns;

use App\Models\MemorizationRecord;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogger;

trait RecordsMemorization
{
    protected function createRecord(array $data, Student $student, User $teacher): MemorizationRecord
    {
        $record = new MemorizationRecord($data);
        $record->student_id = $student->id;
        $record->circle_id = $student->current_circle_id;
        $record->teacher_id = $teacher->id;
        $record->mistakes_count = $data['mistakes_count'] ?? 0;
        $record->shared_with_parent = (bool) ($data['shared_with_parent'] ?? false);
        $record->save();

        return $record;
    }

    protected function updateRecord(MemorizationRecord $record, array $data): void
    {
        $record->fill($data);
        $record->mistakes_count = $data['mistakes_count'] ?? 0;
        $record->shared_with_parent = (bool) ($data['shared_with_parent'] ?? false);
        $record->save();

        AuditLogger::logChanges('memorization.update', "تعديل سجل تسميع للطالب {$record->student->full_name}", $record);
    }
}
