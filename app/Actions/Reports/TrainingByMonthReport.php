<?php

namespace App\Actions\Reports;

use App\Enums\TrainingStatus;
use App\Models\Division;
use App\Models\LdiTraining;
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
     * @return list<array{key: int, label: string, attendances: int, plans: int}>
     */
    public function handle(int $year, ?int $divisionId = null): array
    {
        $counts = $this->approved($year, $divisionId)
            ->get()
            ->groupBy(fn (TrainingRecord $record): int => $record->date_end->month)
            ->map->count();

        $plans = $this->plansByMonth($year, $divisionId);

        $rows = [];

        foreach (range(1, 12) as $month) {
            $rows[] = [
                'key' => $month,
                'label' => CarbonImmutable::create($year, $month, 1)->format('M'),
                'attendances' => (int) ($counts[$month] ?? 0),
                'plans' => (int) ($plans[$month] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * How many planned trainings ran in each month.
     *
     * A plan belongs to the agency rather than to a division, so asking
     * for one division counts the plans that division actually sent
     * somebody to. Otherwise the same twenty-seven plans would sit behind
     * every division's year and say nothing about any of them.
     *
     * @return array<int, int>
     */
    private function plansByMonth(int $year, ?int $divisionId): array
    {
        $counts = LdiTraining::query()
            ->whereYear('date_start', $year)
            ->when($divisionId !== null, fn (Builder $query) => $query->whereHas(
                'trainingRecords.employee',
                fn (Builder $employee) => $employee->where('division_id', $divisionId),
            ))
            ->get()
            ->groupBy(fn (LdiTraining $plan): int => $plan->date_start->month)
            ->map->count();

        $rows = [];

        foreach ($counts as $month => $count) {
            $rows[(int) $month] = $count;
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
     * @return list<array{key: int, label: string, attendances: int, plans: int}>
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
                'plans' => 0,
            ];
        }

        return $rows;
    }

    /**
     * The tallest bar, which every other bar is drawn against. Never zero,
     * so a year with nothing in it does not divide by it.
     *
     * @param  list<array{key: int, label: string, attendances: int, plans: int}>  $rows
     */
    public function peak(array $rows): int
    {
        // A month with nothing in it hands back no rows at all, and max()
        // wants more than the floor to compare against.
        $values = [...array_column($rows, 'attendances'), ...array_column($rows, 'plans')];

        return $values === [] ? 1 : max(1, ...$values);
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
