<?php

namespace App\Policies;

use App\Models\Circle;
use App\Models\User;

class CirclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Circle $circle): bool
    {
        return $user->isAdmin() || ($user->isTeacher() && $circle->teacher_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Circle $circle): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Circle $circle): bool
    {
        return $user->isAdmin();
    }

    /** Taking attendance and logging sessions for the circle. */
    public function manage(User $user, Circle $circle): bool
    {
        return $this->view($user, $circle);
    }
}
