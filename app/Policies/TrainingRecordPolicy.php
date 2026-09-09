<?php

namespace App\Policies;

use App\Enums\TrainingStatus;
use App\Models\Employee;
use App\Models\TrainingRecord;
use App\Models\User;
use App\Workflow\ApprovalRouter;

class TrainingRecordPolicy
{
    public function __construct(private readonly ApprovalRouter $router) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TrainingRecord $record): bool
    {
        return Employee::query()->visibleTo($user)->whereKey($record->employee_id)->exists();
    }

    /**
     * Anyone may record a training. Whose record it is decides the rest.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Submitting for somebody else is an HR and admin matter.
     */
    public function createFor(User $user, Employee $employee): bool
    {
        return $user->isAdminOrHr() || $user->employee?->is($employee) === true;
    }

    /**
     * A record may be corrected only while nobody has acted on it.
     *
     * Once any level has decided, editing would silently change what was
     * already approved, so the record locks. The owner, whoever submitted
     * it, and HR or admin may correct it until then.
     */
    public function update(User $user, TrainingRecord $record): bool
    {
        if ($record->status !== TrainingStatus::Pending || $this->hasBeenActedOn($record)) {
            return false;
        }

        if ($user->isAdminOrHr()) {
            return true;
        }

        return $user->employee?->is($record->employee) === true
            || $user->getKey() === $record->submitted_by;
    }

    /**
     * Uses the loaded relation when the caller eager loaded it, so listing
     * a page of records does not cost one query per row.
     */
    private function hasBeenActedOn(TrainingRecord $record): bool
    {
        if ($record->relationLoaded('approvals')) {
            return $record->approvals->isNotEmpty();
        }

        return $record->approvals()->exists();
    }

    /**
     * The designated head at the record's current level may decide, and so
     * may HR and admin, on anything.
     *
     * The agency asked for that: with most sections lacking a designated
     * head, records were sitting with nobody, and HR had no way to clear
     * them. A decision HR makes is recorded against HR, not against the
     * head who never saw it.
     */
    public function decide(User $user, TrainingRecord $record): bool
    {
        if ($record->status !== TrainingStatus::Pending) {
            return false;
        }

        if ($user->isAdminOrHr()) {
            return true;
        }

        if ($record->current_level === null) {
            return false;
        }

        $employee = $user->employee;

        if ($employee === null) {
            return false;
        }

        $approver = $this->router->approverFor($record->current_level, $record->employee);

        return $approver instanceof Employee && $approver->is($employee);
    }
}
