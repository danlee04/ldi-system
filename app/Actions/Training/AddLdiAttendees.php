<?php

namespace App\Actions\Training;

use App\Enums\TrainingStatus;
use App\Models\Employee;
use App\Models\LdiTraining;
use App\Models\TrainingRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AddLdiAttendees
{
    /**
     * Record attendance for people the agency sent to a planned training.
     *
     * These skip the approval chain: HR planned the training and released
     * the budget, so there is nothing left for a head to decide. Costs are
     * left empty and filled in per attendee afterwards, because the fee
     * and travel expense differ per person.
     *
     * `conducted_by` takes the facilitator, never the development partner:
     * the partner funds the training, and it is the facilitator that a PDS
     * prints under "Conducted/Sponsored by".
     *
     * Somebody already on the list is skipped rather than duplicated.
     *
     * @param  array<int, int>  $employeeIds
     * @return int how many were added
     */
    public function handle(LdiTraining $plan, array $employeeIds, User $addedBy): int
    {
        $existing = $plan->trainingRecords()->pluck('employee_id')->all();

        $wanted = Employee::query()
            ->whereKey($employeeIds)
            ->whereNotIn('id', $existing)
            ->get();

        if ($wanted->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($plan, $wanted, $addedBy): void {
            foreach ($wanted as $employee) {
                TrainingRecord::create([
                    'employee_id' => $employee->getKey(),
                    'ldi_training_id' => $plan->getKey(),
                    'title' => $plan->title,
                    'date_start' => $plan->date_start,
                    'date_end' => $plan->date_end,
                    'hours' => $plan->hours,
                    'ld_type' => $plan->ld_type,
                    'ld_type_other' => $plan->ld_type_other,
                    'conducted_by' => $plan->facilitator,
                    'location' => $plan->location,
                    'cpd_units' => $plan->cpd_units,
                    'status' => TrainingStatus::Approved,
                    'current_level' => null,
                    'submitted_by' => $addedBy->getKey(),
                ]);
            }
        });

        return $wanted->count();
    }
}
