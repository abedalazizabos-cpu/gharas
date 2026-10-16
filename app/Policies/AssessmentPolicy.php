<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\User;

class AssessmentPolicy
{
    public function view(User $user, Assessment $assessment): bool
    {
        return match (true) {
            $user->isAdmin() => true,
            $user->isTeacher() => $assessment->teacher_id === $user->id || $user->teachesStudent($assessment->student),
            $user->isParent() => $assessment->shared_with_parent && $user->isParentOf($assessment->student),
            default => false,
        };
    }

    public function delete(User $user, Assessment $assessment): bool
    {
        return $user->isAdmin();
    }
}
