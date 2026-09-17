<?php

namespace App\Actions\Ldna;

use App\Models\LdnaAssessment;
use App\Models\LdnaCycle;
use App\Models\User;
use App\Workflow\HeadedTeam;
use App\Workflow\LdnaConfirmer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The people an account confirms in the open cycle. The sidebar badge and
 * the confirmations list read the same list, so the two always agree.
 */
class CountLdnaConfirmationsDue
{
    public function __construct(private readonly LdnaConfirmer $confirmer) {}

    /**
     * How many of them are waiting on it.
     *
     * Only the ones who have submitted are counted. A head can do nothing
     * about somebody who has not answered yet, so putting them in the
     * badge would leave a number that never comes down.
     */
    public function handle(User $user): int
    {
        $cycle = LdnaCycle::current();

        if ($cycle === null) {
            return 0;
        }

        return $this->confirmees($user, $cycle)
            ->filter(fn (LdnaAssessment $assessment): bool => $assessment->isSelfSubmitted() && ! $assessment->isConfirmed())
            ->count();
    }

    /**
     * Everybody in the cycle this account confirms, surname first.
     *
     * HR may confirm anybody nobody else does, so it looks at the whole
     * cycle. A head can confirm only somebody inside what they head, so
     * the search starts there rather than with everybody.
     *
     * @return Collection<int, LdnaAssessment>
     */
    public function confirmees(User $user, LdnaCycle $cycle): Collection
    {
        $query = LdnaAssessment::query()
            ->where('ldna_cycle_id', $cycle->getKey())
            ->whereHas('employee', fn (Builder $employee) => $employee->where('is_active', true))
            ->with(['employee.section', 'employee.division']);

        if (! $user->isAdminOrHr()) {
            $team = HeadedTeam::for($user);

            if ($team === null) {
                return collect();
            }

            $query->whereIn('employee_id', $team->employees()->select('id'));
        }

        return $query->get()
            ->filter(fn (LdnaAssessment $assessment): bool => $this->confirmer->confirms($user, $assessment->employee))
            ->sortBy(fn (LdnaAssessment $assessment): string => $assessment->employee->listing_name)
            ->values();
    }
}
