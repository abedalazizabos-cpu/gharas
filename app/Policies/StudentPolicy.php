<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /** Admin: all. Teacher: students in their circles. Parent: own linked children only. */
    public function view(User $user, Student $student): bool
    {
        return match (true) {
            $user->isAdmin() => true,
            $user->isTeacher() => $user->teachesStudent($student),
            $user->isParent() => $user->isParentOf($student),
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Student $student): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->isAdmin();
    }

    /** Editing the student's data and guardians: admin, or the teacher of the student's circle. */
    public function manage(User $user, Student $student): bool
    {
        return $user->isAdmin() || ($user->isTeacher() && $user->teachesStudent($student));
    }

    /** Recording attendance / memorization / assessments for the student. */
    public function record(User $user, Student $student): bool
    {
        return $user->isAdmin() || ($user->isTeacher() && $user->teachesStudent($student));
    }
}
