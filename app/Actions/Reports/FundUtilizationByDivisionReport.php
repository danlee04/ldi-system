<?php

namespace App\Actions\Reports;

use App\Enums\TrainingStatus;
use App\Models\Division;
use App\Models\TrainingRecord;

/**
 * What each division spent on training, quarter by quarter.
 *
 * The cost follows the attendee, not the plan: a division is charged for
 * the people it sent. Only approved records count — an unapproved one is
 * a request, not a cost.
 */
class FundUtilizationByDivisionReport
{
    /**
     * @return list<array{division: string, quarters: array<int, float>, total: float, attendances: int}>
     */
    public function handle(int $year): array
    {
        $divisions = Division::query()->orderBy('code')->get();

        $records = TrainingRecord::query()
            ->where('status', TrainingStatus::Approved)
            ->whereYear('date_end', $year)
            ->with('employee:id,division_id')
            ->get();

        $rows = [];

        foreach ($divisions as $division) {
            $mine = $records->filter(
                fn (TrainingRecord $record): bool => $record->employee?->division_id === $division->getKey(),
            );

            $quarters = [];

            foreach (Quarter::all() as $quarter) {
                $quarters[$quarter] = (float) $mine
                    ->filter(fn (TrainingRecord $record): bool => Quarter::of($record->date_end) === $quarter)
                    ->sum(fn (TrainingRecord $record): float => $this->cost($record));
            }

            $rows[] = [
                'division' => $division->code ?? $division->name,
                'quarters' => $quarters,
                'total' => (float) array_sum($quarters),
                'attendances' => $mine->count(),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{division: string, quarters: array<int, float>, total: float, attendances: int}>  $rows
     * @return list<list<string|int|float>>
     */
    public function toRows(array $rows): array
    {
        $out = [['Division', 'Attendances', 'Q1', 'Q2', 'Q3', 'Q4', 'Total']];

        foreach ($rows as $row) {
            $out[] = [
                $row['division'],
                $row['attendances'],
                $row['quarters'][1],
                $row['quarters'][2],
                $row['quarters'][3],
                $row['quarters'][4],
                $row['total'],
            ];
        }

        return $out;
    }

    private function cost(TrainingRecord $record): float
    {
        return (float) $record->registration_fee + (float) $record->tev + (float) $record->expenses;
    }
}
