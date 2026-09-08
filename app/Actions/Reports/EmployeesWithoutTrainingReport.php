<?php

namespace App\Actions\Reports;

use App\Enums\TrainingStatus;
use App\Models\Employee;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who has nothing on record for a year.
 *
 * The rule is the one HR chose: an employee is short if they finished no
 * approved training that year. A record still waiting for a head does not
 * count — it may yet be rejected.
 */
class EmployeesWithoutTrainingReport
{
    /**
     * @return Collection<int, Employee>
     */
    public function handle(int $year): Collection
    {
        return Employee::query()
            ->active()
            ->whereDoesntHave('trainingRecords', function (Builder $query) use ($year): void {
                $query->where('status', TrainingStatus::Approved)->whereYear('date_end', $year);
            })
            ->with(['section', 'division', 'position'])
            ->get()
            ->sortBy([
                fn (Employee $employee): string => $employee->division->code ?? '',
                fn (Employee $employee): string => $employee->section->name ?? '',
                fn (Employee $employee): string => $employee->last_name,
            ])
            ->values();
    }

    /**
     * The report's own columns, so the screen and the download can never
     * drift apart.
     *
     * @param  Collection<int, Employee>  $employees
     * @return list<list<string>>
     */
    public function toRows(Collection $employees): array
    {
        $rows = [['Division', 'Section', 'Employee no.', 'Employee', 'Position']];

        foreach ($employees as $employee) {
            $rows[] = [
                $employee->division->code ?? '',
                $employee->section->name ?? '',
                $employee->employee_number,
                $employee->listing_name,
                $employee->position->title ?? '',
            ];
        }

        return $rows;
    }

    /**
     * How many of the active roster this covers, so the count reads as a
     * proportion rather than a bare number.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array{without: int, active: int}
     */
    public function summarise(Collection $employees): array
    {
        return [
            'without' => $employees->count(),
            'active' => Employee::query()->active()->count(),
        ];
    }
}
