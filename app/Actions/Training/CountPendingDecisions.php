<?php

namespace App\Actions\Training;

use App\Models\Employee;
use App\Models\TrainingRecord;
use App\Models\User;
use App\Workflow\ApprovalRouter;
use Illuminate\Support\Collection;

/**
 * The pending records this account may decide on.
 *
 * The sidebar asks for the count on every page and a head's dashboard asks
 * for the list, and both have to agree, so both come from `records()`. The
 * relations the routing needs are loaded up front rather than one query
 * per record.
 */
class CountPendingDecisions
{
    public function __construct(private readonly ApprovalRouter $router) {}

    public function handle(User $user): int
    {
        // HR and admin decide on anything, so a count is all that is asked
        // for and there is no need to load a single record to get it.
        if ($user->isAdminOrHr()) {
            return TrainingRecord::query()->pending()->count();
        }

        return $this->records($user)->count();
    }

    /**
     * Oldest first, because the one that has waited longest is the one to
     * decide next.
     *
     * @return Collection<int, TrainingRecord>
     */
    public function records(User $user): Collection
    {
        $pending = TrainingRecord::query()
            ->pending()
            ->with(['employee.section', 'employee.division'])
            ->oldest();

        // HR and admin decide on anything, the unroutable ones included.
        if ($user->isAdminOrHr()) {
            return $pending->get();
        }

        $employee = $user->employee;

        if (! $employee instanceof Employee) {
            return collect();
        }

        return $pending
            ->whereNotNull('current_level')
            ->get()
            ->filter(function (TrainingRecord $record) use ($employee): bool {
                $approver = $this->router->approverFor($record->current_level, $record->employee);

                return $approver instanceof Employee && $approver->is($employee);
            })
            ->values();
    }
}
