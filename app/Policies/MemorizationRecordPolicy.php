<?php

namespace App\Policies;

use App\Models\MemorizationRecord;
use App\Models\Setting;
use App\Models\User;

class MemorizationRecordPolicy
{
    public function view(User $user, MemorizationRecord $record): bool
    {
        return match (true) {
            $user->isAdmin() => true,
            $user->isTeacher() => $record->teacher_id === $user->id || $user->teachesStudent($record->student),
            $user->isParent() => $record->shared_with_parent && $user->isParentOf($record->student),
            default => false,
        };
    }

    /** Teachers can edit their own records within the configured window. */
    public function update(User $user, MemorizationRecord $record): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTeacher()
            && $record->teacher_id === $user->id
            && $record->created_at->gt(now()->subDays((int) Setting::get('teacher_edit_days', '7')));
    }

    /** Deleting educational records is an admin-only action. */
    public function delete(User $user, MemorizationRecord $record): bool
    {
        return $user->isAdmin();
    }
}
