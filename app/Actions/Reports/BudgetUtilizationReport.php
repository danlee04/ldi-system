<?php

namespace App\Actions\Reports;

use App\Enums\TrainingStatus;
use App\Models\BudgetCap;
use App\Models\LdiTraining;

/**
 * What each budget source was allowed, promised and actually spent.
 *
 * Committed is the sum of what the plans set aside; spent is what the
 * attendees really cost. The two differ, and the difference is the point
 * of the report — a plan that budgeted 54,000 and cost 48,200 leaves
 * money that can still be moved.
 *
 * Sources with plans but no cap are listed too, otherwise spending
 * outside a cap would be invisible.
 */
class BudgetUtilizationReport
{
    /**
     * @return list<array{source: string, cap: float|null, committed: float, spent: float, plans: int}>
     */
    public function handle(int $year): array
    {
        $caps = BudgetCap::query()->where('year', $year)->get()->keyBy('budget_source');

        $planned = LdiTraining::query()
            ->whereYear('date_start', $year)
            ->whereNotNull('budget_source')
            ->pluck('budget_source')
            ->merge(LdiTraining::query()
                ->whereYear('date_start', $year)
                ->whereNotNull('other_budget_source')
                ->pluck('other_budget_source'));

        $sources = $caps->keys()
            ->merge($planned)
            ->unique()
            ->sort()
            ->values();

        $rows = [];

        foreach ($sources as $value) {
            $source = (string) $value;

            $ofSource = LdiTraining::query()
                ->where('budget_source', $source)
                ->whereYear('date_start', $year);

            $cap = $caps->get($source);

            $rows[] = [
                'source' => $source,
                'cap' => $cap === null ? null : (float) $cap->amount,
                'committed' => (float) (clone $ofSource)->sum('budget'),
                'spent' => $this->spentOn($source, $year),
                'plans' => (clone $ofSource)->count(),
            ];
        }

        return $rows;
    }

    /**
     * The report's own columns, so the screen and the download can never
     * drift apart.
     *
     * @param  list<array{source: string, cap: float|null, committed: float, spent: float, plans: int}>  $rows
     * @return list<list<string|int|float>>
     */
    public function toRows(array $rows): array
    {
        $out = [['Budget source', 'Plans', 'Cap', 'Committed', 'Spent', 'Unspent']];

        foreach ($rows as $row) {
            $out[] = [
                $row['source'],
                $row['plans'],
                $row['cap'] ?? '',
                $row['committed'],
                $row['spent'],
                $row['committed'] - $row['spent'],
            ];
        }

        return $out;
    }

    /**
     * What the attendees of this source's plans actually cost.
     *
     * A plan may name a second fund, but not how much came from it, so
     * the spend stays with the plan's own source rather than being split
     * on a guess.
     */
    private function spentOn(string $source, int $year): float
    {
        return (float) LdiTraining::query()
            ->where('budget_source', $source)
            ->whereYear('date_start', $year)
            ->get()
            ->sum(fn (LdiTraining $plan): float => (float) $plan->trainingRecords()
                ->where('status', TrainingStatus::Approved)
                ->selectRaw('COALESCE(SUM(COALESCE(registration_fee, 0) + COALESCE(tev, 0) + COALESCE(expenses, 0)), 0) as total')
                ->value('total'));
    }
}
