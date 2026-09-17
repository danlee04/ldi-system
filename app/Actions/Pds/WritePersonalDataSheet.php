<?php

namespace App\Actions\Pds;

use App\Models\Employee;
use App\Models\PersonalDataSheet;

class WritePersonalDataSheet
{
    /**
     * Write part of the employee's sheet, leaving the rest of the row
     * alone. Section I, the family background and page 4 are three forms
     * over one record, and each saves only the fields it shows.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Employee $employee, array $attributes): PersonalDataSheet
    {
        $sheet = PersonalDataSheet::updateOrCreate(
            ['employee_id' => $employee->getKey()],
            collect($attributes)
                ->except('employee_id')
                ->map(fn (mixed $value): mixed => $value === '' ? null : $value)
                ->all(),
        );

        $employee->unsetRelation('personalDataSheet');

        return $sheet;
    }
}
