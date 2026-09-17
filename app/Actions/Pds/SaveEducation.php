<?php

namespace App\Actions\Pds;

use App\Models\Employee;
use App\Models\EmployeeEducation;
use Illuminate\Support\Facades\DB;

class SaveEducation
{
    /**
     * Write Section III, which prints one line per level whether or not
     * the employee reached it.
     *
     * A level left entirely blank is not an empty line but no line at
     * all: its row is deleted, so the form prints nothing against it and
     * the count of what is filled in stays honest.
     *
     * @param  array<string, array<string, mixed>>  $levels  one set of fields per education level
     */
    public function handle(Employee $employee, array $levels): void
    {
        DB::transaction(function () use ($employee, $levels): void {
            foreach ($levels as $level => $row) {
                $values = collect($row)
                    ->map(fn (mixed $value): mixed => $value === '' ? null : $value)
                    ->all();

                if (collect($values)->filter()->isEmpty()) {
                    EmployeeEducation::query()
                        ->where('employee_id', $employee->getKey())
                        ->where('level', $level)
                        ->delete();

                    continue;
                }

                EmployeeEducation::updateOrCreate(
                    ['employee_id' => $employee->getKey(), 'level' => $level],
                    $values,
                );
            }
        });

        $employee->unsetRelation('educations');
    }
}
