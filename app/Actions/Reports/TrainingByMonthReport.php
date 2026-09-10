<?php

namespace App\Actions\Reports;

use App\Enums\TrainingStatus;
use App\Models\Division;
use App\Models\TrainingRecord;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

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
     * @return list<array{key: int, label: string, attendances: int}>
     */
    public function handle(int $year, ?int $divisionId = null): array
    {
        $counts = $this->approved($year, $divisionId)
            ->get()
            ->groupBy(fn (TrainingRecord $record): int => $record->date_end->month)
            ->map->count();

        $rows = [];

        foreach (range(1, 12) as $month) {
            $rows[] = [
                'key' => $month,
                'label' => CarbonImmutable::create($year, $month, 1)->format('M'),
                'attendances' => (int) ($counts[$month] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * One month, broken down by division.
     *
     * A month asked for on its own is one bar, which is a number wearing a
     * chart's clothes. The question behind picking a month is who was in
     * it, so that is what comes back.
     *
     * @return list<array{key: int, label: string, attendances: int}>
     */
    public function forMonth(int $year, int $month, ?int $divisionId = null): array
    {
        // Grouped by the key rather than the name: an employee with no
        // division still has to appear somewhere, and the id says so
        // without asking a relation that may not be there.
        $counts = $this->approved($year, $divisionId)
            ->whereMonth('date_end', $month)
            ->with('employee')
            ->get()
            ->groupBy(fn (TrainingRecord $record): int => $record->employee->division_id ?? 0)
            ->map->count()
            ->sortDesc();

        $names = Division::query()->whereKey($counts->keys())->pluck('name', 'id');

        $rows = [];

        foreach ($counts as $id => $count) {
            $rows[] = [
                'key' => (int) $id,
                'label' => (string) ($names[$id] ?? __('No division')),
                'attendances' => $count,
            ];
        }

        return $rows;
    }

    /**
     * The tallest bar, which every other bar is drawn against. Never zero,
     * so a year with nothing in it does not divide by it.
     *
     * @param  list<array{key: int, label: string, attendances: int}>  $rows
     */
    public function peak(array $rows): int
    {
        return max(1, ...array_column($rows, 'attendances'), ...[0]);
    }

    /**
     * @return Builder<TrainingRecord>
     */
    private function approved(int $year, ?int $divisionId): Builder
    {
        return TrainingRecord::query()
            ->where('status', TrainingStatus::Approved)
            ->whereYear('date_end', $year)
            ->when($divisionId !== null, fn (Builder $query) => $query->whereHas(
                'employee',
                fn (Builder $employee) => $employee->where('division_id', $divisionId),
            ));
    }
}
