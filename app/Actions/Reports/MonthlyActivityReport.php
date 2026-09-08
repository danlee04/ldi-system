<?php

namespace App\Actions\Reports;

use App\Enums\TrainingStatus;
use App\Models\TrainingRecord;
use Illuminate\Support\Collection;

/**
 * What training actually happened in one month.
 *
 * A training counts for the month it ended in, not the month it was
 * recorded, so a submission entered late still lands where it belongs.
 * Only approved attendance counts: a pending record is not yet a fact.
 */
class MonthlyActivityReport
{
    /**
     * @return Collection<int, TrainingRecord>
     */
    public function handle(int $year, int $month): Collection
    {
        return TrainingRecord::query()
            ->where('status', TrainingStatus::Approved)
            ->whereYear('date_end', $year)
            ->whereMonth('date_end', $month)
            ->with(['employee.section', 'employee.division'])
            ->get()
            ->sortBy([
                fn (TrainingRecord $record): string => $record->employee->division->code ?? '',
                fn (TrainingRecord $record): string => $record->employee->last_name,
                fn (TrainingRecord $record): string => $record->title,
            ])
            ->values();
    }

    /**
     * The report's own columns, so the screen and the download can never
     * drift apart.
     *
     * @param  Collection<int, TrainingRecord>  $records
     * @return list<list<string|int|float>>
     */
    public function toRows(Collection $records): array
    {
        $rows = [['Division', 'Section', 'Employee', 'Training', 'Inclusive dates', 'Hours', 'Type of LD', 'Conducted by', 'Cost']];

        foreach ($records as $record) {
            $rows[] = [
                $record->employee->division->code ?? '',
                $record->employee->section->name ?? '',
                $record->employee->listing_name,
                $record->title,
                $record->inclusive_dates,
                $record->hours,
                $record->ld_type_label,
                $record->conducted_by,
                (float) $record->registration_fee + (float) $record->tev + (float) $record->expenses,
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, TrainingRecord>  $records
     * @return array{attendances: int, trainings: int, employees: int, hours: int, cost: float}
     */
    public function summarise(Collection $records): array
    {
        return [
            'attendances' => $records->count(),
            'trainings' => $records->pluck('title')->unique()->count(),
            'employees' => $records->pluck('employee_id')->unique()->count(),
            'hours' => (int) $records->sum('hours'),
            'cost' => $records->sum(fn (TrainingRecord $record): float => (float) $record->registration_fee
                + (float) $record->tev
                + (float) $record->expenses),
        ];
    }
}
