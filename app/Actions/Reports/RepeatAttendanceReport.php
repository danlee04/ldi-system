<?php

namespace App\Actions\Reports;

use App\Enums\TrainingStatus;
use App\Models\Employee;
use App\Models\TrainingRecord;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who the year's training was new to, and who had been before.
 *
 * A first-timer here is somebody whose first approved record ended in the
 * year being read. Anyone with something earlier is a repeat — the number
 * to watch when the same faces keep being sent.
 */
class RepeatAttendanceReport
{
    /**
     * @return list<array{employee: Employee, attendances: int, earlier: int, first_timer: bool}>
     */
    public function handle(int $year): array
    {
        $employees = Employee::query()
            ->active()
            ->whereHas('trainingRecords', fn (Builder $query) => $query
                ->where('status', TrainingStatus::Approved)
                ->whereYear('date_end', $year))
            ->with('division')
            ->get()
            ->sortBy([
                fn (Employee $employee): string => $employee->division->code ?? '',
                fn (Employee $employee): string => $employee->last_name,
            ])
            ->values();

        $thisYear = $this->countBy($employees, $year, '=');
        $earlier = $this->countBy($employees, $year, '<');

        return array_values($employees
            ->map(function (Employee $employee) use ($thisYear, $earlier): array {
                $before = $earlier[$employee->getKey()] ?? 0;

                return [
                    'employee' => $employee,
                    'attendances' => $thisYear[$employee->getKey()] ?? 0,
                    'earlier' => $before,
                    'first_timer' => $before === 0,
                ];
            })
            ->all());
    }

    /**
     * @param  list<array{employee: Employee, attendances: int, earlier: int, first_timer: bool}>  $rows
     * @return list<list<string|int>>
     */
    public function toRows(array $rows): array
    {
        $out = [['Division', 'Employee', 'Attendances this year', 'Earlier attendances', 'Standing']];

        foreach ($rows as $row) {
            $out[] = [
                $row['employee']->division->code ?? '',
                $row['employee']->listing_name,
                $row['attendances'],
                $row['earlier'],
                $row['first_timer'] ? 'First-timer' : 'Repeat',
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{employee: Employee, attendances: int, earlier: int, first_timer: bool}>  $rows
     * @return array{attendees: int, first_timers: int, repeats: int}
     */
    public function summarise(array $rows): array
    {
        $firstTimers = count(array_filter($rows, fn (array $row): bool => $row['first_timer']));

        return [
            'attendees' => count($rows),
            'first_timers' => $firstTimers,
            'repeats' => count($rows) - $firstTimers,
        ];
    }

    /**
     * How many approved records each of them has, in or before the year.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<int, int>
     */
    private function countBy(Collection $employees, int $year, string $operator): array
    {
        return TrainingRecord::query()
            ->where('status', TrainingStatus::Approved)
            ->whereIn('employee_id', $employees->modelKeys())
            ->when(
                $operator === '=',
                fn (Builder $query) => $query->whereYear('date_end', $year),
                fn (Builder $query) => $query->whereYear('date_end', '<', $year),
            )
            ->selectRaw('employee_id, COUNT(*) as total')
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }
}
