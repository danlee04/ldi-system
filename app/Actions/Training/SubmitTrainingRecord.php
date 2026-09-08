<?php

namespace App\Actions\Training;

use App\Enums\TrainingStatus;
use App\Models\Employee;
use App\Models\TrainingRecord;
use App\Models\User;
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
        return TrainingRecord::create([
            ...$attributes,
            'employee_id' => $employee->getKey(),
            'submitted_by' => $submittedBy->getKey(),
            'status' => TrainingStatus::Pending,
            'current_level' => $this->router->firstLevelFor($employee),
        ]);
    }
}
