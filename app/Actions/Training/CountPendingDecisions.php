<?php

namespace App\Actions\Training;

use App\Models\Employee;
use App\Models\TrainingRecord;
use App\Models\User;
use App\Workflow\ApprovalRouter;

/**
 * How many pending records this account may decide on.
 *
 * The sidebar asks this on every page, so the relations the routing needs
 * are loaded up front rather than one query per record.
 */
class CountPendingDecisions
{
    public function __construct(private readonly ApprovalRouter $router) {}

    public function handle(User $user): int
    {
        // HR and admin decide on anything, the unroutable ones included.
        if ($user->isAdminOrHr()) {
            return TrainingRecord::query()->pending()->count();
        }

        $employee = $user->employee;

        if (! $employee instanceof Employee) {
            return 0;
        }

        return TrainingRecord::query()
            ->pending()
            ->whereNotNull('current_level')
            ->with(['employee.section', 'employee.division'])
            ->get()
            ->filter(function (TrainingRecord $record) use ($employee): bool {
                $approver = $this->router->approverFor($record->current_level, $record->employee);

                return $approver instanceof Employee && $approver->is($employee);
            })
            ->count();
    }
}
