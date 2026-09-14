<?php

namespace App\Policies;

use App\Models\LdnaAssessment;
use App\Models\User;
use App\Workflow\LdnaRater;

/**
 * Who may do what with somebody's needs assessment.
 *
 * The actions call these, not only the screens, so a request built by
 * hand meets the same rule as a button.
 */
class LdnaAssessmentPolicy
{
    public function __construct(private readonly LdnaRater $rater) {}

    /**
     * The person themselves, whoever rates them, and HR.
     */
    public function view(User $user, LdnaAssessment $assessment): bool
    {
        return $this->isOwner($user, $assessment) || $this->viewAsRater($user, $assessment);
    }

    /**
     * The rating page: whoever rates them, and HR, who oversees every one.
     */
    public function viewAsRater(User $user, LdnaAssessment $assessment): bool
    {
        return $user->isAdminOrHr() || $this->rater->rates($user, $assessment->employee);
    }

    public function selfRate(User $user, LdnaAssessment $assessment): bool
    {
        return $this->isOwner($user, $assessment) && $assessment->cycle->isOpen();
    }

    public function rate(User $user, LdnaAssessment $assessment): bool
    {
        return $assessment->cycle->isOpen() && $this->rater->rates($user, $assessment->employee);
    }

    /**
     * What the supervisor found, and the gap, once nothing more can change.
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
