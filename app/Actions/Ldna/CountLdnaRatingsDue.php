<?php

namespace App\Actions\Ldna;

use App\Models\LdnaAssessment;
use App\Models\LdnaCycle;
use App\Models\User;
use App\Workflow\HeadedTeam;
use App\Workflow\LdnaRater;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The people an account rates in the open cycle. The sidebar badge and
 * the ratings list read the same list, so the two always agree.
 */
class CountLdnaRatingsDue
{
    public function __construct(private readonly LdnaRater $rater) {}

    /**
     * How many of them it has still to rate.
     */
    public function handle(User $user): int
    {
        $cycle = LdnaCycle::current();

        if ($cycle === null) {
            return 0;
        }

        return $this->ratees($user, $cycle)->reject(fn (LdnaAssessment $assessment): bool => $assessment->isRated())->count();
    }

    /**
     * Everybody in the cycle this account rates, surname first.
     *
     * HR may rate anybody nobody else rates, so it looks at the whole
     * cycle. A head can rate only somebody inside what they head, so the
     * search starts there rather than with everybody.
     *
     * @return Collection<int, LdnaAssessment>
     */
    public function ratees(User $user, LdnaCycle $cycle): Collection
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
            ->filter(fn (LdnaAssessment $assessment): bool => $this->rater->rates($user, $assessment->employee))
            ->sortBy(fn (LdnaAssessment $assessment): string => $assessment->employee->listing_name)
            ->values();
    }
}
