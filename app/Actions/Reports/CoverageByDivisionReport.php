<?php

namespace App\Actions\Reports;

use App\Enums\TrainingStatus;
use App\Models\Division;
use App\Models\Employee;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * How much of each division the year's training actually reached.
 *
 * The same rule as the without-training report, read the other way round:
 * an employee is covered if one approved record ended that year. Divisions
 * with nobody in them are left out — a percentage of nothing says nothing.
 */
class CoverageByDivisionReport
{
    /**
     * @return list<array{division: string, employees: int, covered: int, percentage: float}>
     */
    public function handle(int $year): array
    {
        $divisions = Division::query()->orderBy('code')->get();
        $rows = [];

        foreach ($divisions as $division) {
            $employees = Employee::query()->active()->where('division_id', $division->getKey())->count();

            if ($employees === 0) {
                continue;
            }

            $covered = Employee::query()
                ->active()
                ->where('division_id', $division->getKey())
                ->whereHas('trainingRecords', fn (Builder $query) => $query
                    ->where('status', TrainingStatus::Approved)
                    ->whereYear('date_end', $year))
                ->count();

            $rows[] = [
                'division' => $division->code ?? $division->name,
                'employees' => $employees,
                'covered' => $covered,
                'percentage' => round($covered / $employees * 100, 1),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{division: string, employees: int, covered: int, percentage: float}>  $rows
     * @return list<list<string|int|float>>
     */
    public function toRows(array $rows): array
    {
        $out = [['Division', 'Employees', 'With training', 'Without', 'Coverage %']];

        foreach ($rows as $row) {
            $out[] = [
                $row['division'],
                $row['employees'],
                $row['covered'],
                $row['employees'] - $row['covered'],
                $row['percentage'],
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{division: string, employees: int, covered: int, percentage: float}>  $rows
     * @return array{employees: int, covered: int, percentage: float}
     */
    public function summarise(array $rows): array
    {
        $employees = (int) collect($rows)->sum('employees');
        $covered = (int) collect($rows)->sum('covered');

        return [
            'employees' => $employees,
            'covered' => $covered,
            'percentage' => $employees === 0 ? 0.0 : round($covered / $employees * 100, 1),
        ];
    }
}
