<?php

namespace App\Actions\Reports;

use App\Enums\TrainingStatus;
use App\Models\TrainingRecord;

/**
 * What each provider was paid, and what one seat with them cost.
 *
 * Grouped by whoever conducted the training, because that is the name the
 * agency negotiates with. A free provider is worth seeing as much as an
 * expensive one, so nothing is dropped for costing nothing.
 */
class CostPerParticipantReport
{
    /**
     * @return list<array{provider: string, attendances: int, employees: int, hours: int, cost: float, per_participant: float}>
     */
    public function handle(int $year): array
    {
        $records = TrainingRecord::query()
            ->where('status', TrainingStatus::Approved)
            ->whereYear('date_end', $year)
            ->get();

        $rows = [];

        foreach ($records->groupBy('conducted_by') as $provider => $group) {
            $cost = (float) $group->sum(fn (TrainingRecord $record): float => (float) $record->registration_fee
                + (float) $record->tev
                + (float) $record->expenses);

            $rows[] = [
                'provider' => (string) $provider,
                'attendances' => $group->count(),
                'employees' => $group->pluck('employee_id')->unique()->count(),
                'hours' => (int) $group->sum('hours'),
                'cost' => $cost,
                'per_participant' => $group->count() === 0 ? 0.0 : round($cost / $group->count(), 2),
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['cost'] <=> $a['cost']);

        return $rows;
    }

    /**
     * @param  list<array{provider: string, attendances: int, employees: int, hours: int, cost: float, per_participant: float}>  $rows
     * @return list<list<string|int|float>>
     */
    public function toRows(array $rows): array
    {
        $out = [['Provider', 'Attendances', 'Employees', 'Hours', 'Total cost', 'Cost per participant']];

        foreach ($rows as $row) {
            $out[] = [
                $row['provider'],
                $row['attendances'],
                $row['employees'],
                $row['hours'],
                $row['cost'],
                $row['per_participant'],
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{provider: string, attendances: int, employees: int, hours: int, cost: float, per_participant: float}>  $rows
     * @return array{providers: int, attendances: int, cost: float, per_participant: float}
     */
    public function summarise(array $rows): array
    {
        $attendances = (int) collect($rows)->sum('attendances');
        $cost = (float) collect($rows)->sum('cost');

        return [
            'providers' => count($rows),
            'attendances' => $attendances,
            'cost' => $cost,
            'per_participant' => $attendances === 0 ? 0.0 : round($cost / $attendances, 2),
        ];
    }
}
