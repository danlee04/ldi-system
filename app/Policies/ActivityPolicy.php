<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

/**
 * The calendar is the whole office's to read and one person's to keep.
 *
 * HR and admin keep it by virtue of their role. Beyond them, an account
 * is trusted with it one at a time, by the tick an administrator puts on
 * it — see User::managesCalendar().
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Activity $activity): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->managesCalendar();
    }

    /**
     * Whoever keeps the calendar keeps all of it. An activity is a notice
     * to the office rather than anybody's own record, so there is nothing
     * here that only its author should be able to correct.
     */
    public function update(User $user, Activity $activity): bool
    {
        return $user->managesCalendar();
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $user->managesCalendar();
    }
}
