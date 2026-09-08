<?php

namespace App\Policies;

use App\Models\LdiTraining;
use App\Models\User;

/**
 * Planned trainings and their budgets are an HR matter throughout.
 *
 * Employees never touch this: they see their own attendance as an ordinary
 * training record, and cannot tell it came from a plan.
 */
class LdiTrainingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminOrHr();
    }

    public function view(User $user, LdiTraining $ldiTraining): bool
    {
        return $user->isAdminOrHr();
    }

    public function create(User $user): bool
    {
        return $user->isAdminOrHr();
    }

    public function update(User $user, LdiTraining $ldiTraining): bool
    {
        return $user->isAdminOrHr();
    }

    public function delete(User $user, LdiTraining $ldiTraining): bool
    {
        return $user->isAdminOrHr();
    }
}
