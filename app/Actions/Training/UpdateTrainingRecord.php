<?php

namespace App\Actions\Training;

use App\Models\Employee;
use App\Models\TrainingRecord;
use App\Workflow\ApprovalRouter;

class UpdateTrainingRecord
{
    public function __construct(private readonly ApprovalRouter $router) {}

    /**
     * Correct a record that nobody has acted on yet.
     *
     * The approver is recomputed on every edit: HR may move the record to
     * a different employee, and that employee may sit under a different
     * head. Nothing has been decided yet, so restarting the route costs
     * no history.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(TrainingRecord $record, Employee $employee, array $attributes): TrainingRecord
    {
        $record->update([
            ...$attributes,
            'employee_id' => $employee->getKey(),
            'current_level' => $this->router->firstLevelFor($employee),
        ]);

        return $record->refresh();
    }
}
