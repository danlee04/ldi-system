<?php

namespace App\Actions\Employees;

use App\Models\Employee;
use App\Models\EmployeeEligibility;
use Illuminate\Support\Facades\DB;

class SaveEmployee
{
    /**
     * Write one line of the roster, and the one eligibility the form asks
     * for.
     *
     * The optional parts of a name and the two assignments are stored as
     * nothing rather than as an empty string, so a person with no middle
     * name reads the same whether HR left the box alone or cleared it.
     *
     * The division is not written here: the model keeps it in step with
     * the section on save, so the form's division picker only narrows
     * which sections are offered.
     *
     * @param  array<string, mixed>  $attributes  the form's fields, with positionId and employeeSectionId as the page names them
     */
    public function handle(?Employee $employee, array $attributes): Employee
    {
        return DB::transaction(function () use ($employee, $attributes): Employee {
            $saved = $employee ?? new Employee;

            $saved->fill([
                'first_name' => $attributes['first_name'],
                'middle_name' => $attributes['middle_name'] ?: null,
                'last_name' => $attributes['last_name'],
                'suffix' => $attributes['suffix'] ?: null,
                'gender' => $attributes['gender'] ?: null,
                'position_id' => $attributes['positionId'],
                'item_number' => $attributes['item_number'] ?: null,
                'section_id' => $attributes['employeeSectionId'],
                'employment_status' => $attributes['employment_status'],
            ])->save();

            $this->writeEligibility($saved, $attributes);

            return $saved;
        });
    }

    /**
     * The roster's eligibility column reads the employee's own PDS, so
     * this writes into it rather than keeping a second copy.
     *
     * Only the first line is touched, and only when an eligibility is
     * chosen. Left empty, the PDS is left alone: the employee may have
     * typed a rating, a place of examination and a licence number against
     * that line, and none of that is on this form to put back.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function writeEligibility(Employee $employee, array $attributes): void
    {
        if (blank($attributes['eligibilityId'])) {
            return;
        }

        $line = $employee->eligibilities()->oldest('id')->first() ?? new EmployeeEligibility;

        $line->employee_id = $employee->getKey();
        $line->eligibility_id = (int) $attributes['eligibilityId'];
        $line->date_of_validity = $attributes['eligibilityExpiresOn'] ?: null;

        $line->save();

        $employee->unsetRelation('eligibilities');
    }
}
