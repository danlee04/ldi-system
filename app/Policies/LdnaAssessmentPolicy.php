<?php

namespace App\Policies;

use App\Models\LdnaAssessment;
use App\Models\User;
use App\Workflow\LdnaConfirmer;

/**
 * Who may do what with somebody's needs assessment.
 *
 * The actions call these, not only the screens, so a request built by
 * hand meets the same rule as a button.
 */
class LdnaAssessmentPolicy
{
    public function __construct(private readonly LdnaConfirmer $confirmer) {}

    /**
     * The person themselves, whoever confirms it, and HR.
     */
    public function view(User $user, LdnaAssessment $assessment): bool
    {
        return $this->isOwner($user, $assessment) || $this->viewAsConfirmer($user, $assessment);
    }

    /**
     * The review page: whoever confirms it, and HR, who oversees every one.
     */
    public function viewAsConfirmer(User $user, LdnaAssessment $assessment): bool
    {
        return $user->isAdminOrHr() || $this->confirmer->confirms($user, $assessment->employee);
    }

    public function selfRate(User $user, LdnaAssessment $assessment): bool
    {
        return $this->isOwner($user, $assessment) && $assessment->cycle->isOpen();
    }

    public function confirm(User $user, LdnaAssessment $assessment): bool
    {
        return $assessment->cycle->isOpen() && $this->confirmer->confirms($user, $assessment->employee);
    }

    /**
     * Their own gaps, once nothing more can change.
     */
    public function seeOwnResult(User $user, LdnaAssessment $assessment): bool
    {
        return $this->isOwner($user, $assessment) && $assessment->cycle->hasClosed();
    }

    private function isOwner(User $user, LdnaAssessment $assessment): bool
    {
        return $user->employee !== null && $user->employee->getKey() === $assessment->employee_id;
    }
}
