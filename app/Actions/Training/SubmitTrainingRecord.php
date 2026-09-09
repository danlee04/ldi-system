<?php

namespace App\Actions\Training;

use App\Enums\TrainingStatus;
use App\Models\Employee;
use App\Models\TrainingRecord;
use App\Models\User;
use App\Notifications\TrainingAwaitsDecision;
use App\Workflow\ApprovalRouter;

class SubmitTrainingRecord
{
    public function __construct(private readonly ApprovalRouter $router) {}

    /**
     * Record a training an employee attended and start its approval.
     *
     * When nobody can approve it the record is still saved, pending and
     * without a level, so HR can see it and designate a head.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Employee $employee, array $attributes, User $submittedBy): TrainingRecord
    {
        $level = $this->router->firstLevelFor($employee);

        $record = TrainingRecord::create([
            ...$attributes,
            'employee_id' => $employee->getKey(),
            'submitted_by' => $submittedBy->getKey(),
            'status' => TrainingStatus::Pending,
            'current_level' => $level,
        ]);

        if ($level !== null) {
            $this->router->approverFor($level, $employee)?->user?->notify(
                new TrainingAwaitsDecision($record),
            );
        }

        return $record;
    }
}
