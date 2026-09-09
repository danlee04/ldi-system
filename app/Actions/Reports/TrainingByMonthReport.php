<?php

namespace App\Actions\Reports;

use App\Enums\TrainingStatus;
use App\Models\TrainingRecord;
use Carbon\CarbonImmutable;

/**
 * Attendances per month for one year.
 *
 * Every month is returned, including the empty ones — a year with a hole
 * in July should look like a year with a hole in July, not like a year of
 * eleven months.
 */
class TrainingByMonthReport
{
    /**
     * @return list<array{month: int, label: string, attendances: int}>
     */
    public function handle(int $year): array
    {
        $counts = TrainingRecord::query()
            ->where('status', TrainingStatus::Approved)
            ->whereYear('date_end', $year)
            ->get()
            ->groupBy(fn (TrainingRecord $record): int => $record->date_end->month)
            ->map->count();

        $rows = [];

        foreach (range(1, 12) as $month) {
            $rows[] = [
                'month' => $month,
                'label' => CarbonImmutable::create($year, $month, 1)->format('M'),
                'attendances' => (int) ($counts[$month] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * The tallest bar, which every other bar is drawn against. Never zero,
     * so a year with nothing in it does not divide by it.
     *
     * @param  list<array{month: int, label: string, attendances: int}>  $rows
     */
    public function peak(array $rows): int
    {
        return max(1, ...array_column($rows, 'attendances'));
    }
}
