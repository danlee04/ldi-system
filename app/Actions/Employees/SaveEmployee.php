<?php

namespace App\Actions\Employees;

use App\Models\Employee;

class SaveEmployee
{
    /**
     * Write one line of the roster.
     *
     * The optional parts of a name and the two assignments are stored as
     * nothing rather than as an empty string, so a person with no middle
     * name reads the same whether HR left the box alone or cleared it.
     *
     * @param  array<string, mixed>  $attributes  the form's fields, with positionId and employeeSectionId as the page names them
     */
    public function handle(?Employee $employee, array $attributes): Employee
    {
        $saved = $employee ?? new Employee;

        $saved->fill([
            'employee_number' => $attributes['employee_number'],
            'first_name' => $attributes['first_name'],
            'middle_name' => $attributes['middle_name'] ?: null,
            'last_name' => $attributes['last_name'],
            'suffix' => $attributes['suffix'] ?: null,
            'gender' => $attributes['gender'] ?: null,
            'position_id' => $attributes['positionId'],
            'section_id' => $attributes['employeeSectionId'],
            'employment_status' => $attributes['employment_status'],
            'date_hired' => $attributes['date_hired'] ?: null,
            'is_active' => $attributes['is_active'],
        ])->save();

        return $saved;
    }
}
