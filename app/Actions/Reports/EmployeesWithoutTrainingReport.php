<?php

namespace App\Actions\Reports;

use App\Enums\TrainingStatus;
use App\Models\Employee;
use App\Models\TrainingRecord;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who has nothing on record for a year.
 *
 * The rule is the one HR chose: an employee is short if they finished no
 * approved training that year. A record still waiting for a head does not
 * count — it may yet be rejected.
 *
 * Each line also carries the last time they did attend something, which
 * is the difference between somebody who missed a year and somebody who
 * has never been sent at all.
 */
class EmployeesWithoutTrainingReport
{
    /**
     * @return list<array{employee: Employee, last_training: CarbonImmutable|null}>
     */
    public function handle(int $year, ?int $divisionId = null, ?int $sectionId = null): array
    {
        $employees = Employee::query()
            ->active()
            ->whereDoesntHave('trainingRecords', function (Builder $query) use ($year): void {
                $query->where('status', TrainingStatus::Approved)->whereYear('date_end', $year);
            })
            ->when($divisionId !== null, fn (Builder $query) => $query->where('division_id', $divisionId))
            ->when($sectionId !== null, fn (Builder $query) => $query->where('section_id', $sectionId))
            ->with(['section', 'division', 'position'])
            ->get()
            ->sortBy([
                fn (Employee $employee): string => $employee->division->code ?? '',
                fn (Employee $employee): string => $employee->section->name ?? '',
                fn (Employee $employee): string => $employee->last_name,
            ])
            ->values();

        $last = $this->lastTrainings($employees);

        return array_values($employees
            ->map(fn (Employee $employee): array => [
                'employee' => $employee,
                'last_training' => $last[$employee->getKey()] ?? null,
            ])
            ->all());
    }

    /**
     * The report's own columns, so the screen and the download can never
     * drift apart.
     *
     * @param  list<array{employee: Employee, last_training: CarbonImmutable|null}>  $rows
     * @return list<list<string>>
     */
    public function toRows(array $rows): array
    {
        $out = [['Division', 'Section', 'Employee no.', 'Employee', 'Position', 'Last training']];

        foreach ($rows as $row) {
            $employee = $row['employee'];

            $out[] = [
                $employee->division->code ?? '',
                $employee->section->name ?? '',
                $employee->employee_number,
                $employee->listing_name,
                $employee->position->title ?? '',
                $row['last_training']?->format('d M Y') ?? 'Never',
            ];
        }

        return $out;
    }

    /**
     * How many of the active roster this covers, so the count reads as a
     * proportion rather than a bare number, and how many of them have
     * never been sent to anything at all.
     *
     * @param  list<array{employee: Employee, last_training: CarbonImmutable|null}>  $rows
     * @return array{without: int, active: int, never: int}
     */
    public function summarise(array $rows): array
    {
        return [
            'without' => count($rows),
            'active' => Employee::query()->active()->count(),
            'never' => count(array_filter($rows, fn (array $row): bool => $row['last_training'] === null)),
        ];
    }

    /**
     * The last approved training of each of them, in one query rather
     * than one per person.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<int, CarbonImmutable>
     */
    private function lastTrainings(Collection $employees): array
    {
        return TrainingRecord::query()
            ->where('status', TrainingStatus::Approved)
            ->whereIn('employee_id', $employees->modelKeys())
            ->selectRaw('employee_id, MAX(date_end) as last_date')
            ->groupBy('employee_id')
            ->pluck('last_date', 'employee_id')
            ->map(fn (mixed $date): CarbonImmutable => CarbonImmutable::parse((string) $date))
            ->all();
    }
}
