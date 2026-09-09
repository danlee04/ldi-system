<?php

namespace App\Actions\Reports;

use App\Enums\TrainingStatus;
use App\Models\Employee;
use App\Models\LdiTraining;
use App\Models\TrainingRecord;

/**
 * The three headline totals, each with the breakdown behind it.
 *
 * A total on its own invites the next question, so every one of these
 * carries the parts it is made of.
 */
class AgencyTotalsReport
{
    /**
     * The name a row takes when the field behind it was left empty. It is
     * shown rather than dropped — a gap in the records is a fact about
     * them, and hiding it makes the total look unaccounted for.
     */
    public const NOT_STATED = 'Not stated';

    /**
     * The agency's own budget line, as it is spelled in the plans.
     */
    public const HR_SOURCE = 'Human Resource';

    /**
     * @return array{total: int, rows: list<array{label: string, count: int}>}
     */
    public function employeesByDivision(): array
    {
        $employees = Employee::query()->active()->with('division')->get();

        return [
            'total' => $employees->count(),
            'rows' => $this->rank(
                $employees
                    ->groupBy(fn (Employee $employee): string => $employee->division->code ?? self::NOT_STATED)
                    ->map->count()
                    ->all(),
            ),
        ];
    }

    /**
     * @return array{total: int, rows: list<array{label: string, count: int}>}
     */
    public function plansByCommunication(int $year): array
    {
        $plans = LdiTraining::query()->whereYear('date_start', $year)->get();

        return [
            'total' => $plans->count(),
            'rows' => $this->rank(
                $plans
                    ->groupBy(fn (LdiTraining $plan): string => $plan->training_communication ?: self::NOT_STATED)
                    ->map->count()
                    ->all(),
            ),
        ];
    }

    /**
     * What the year cost, split by the fund that carried it.
     *
     * The agency's own HR budget is separated from everything else,
     * because that is the split the office is asked about.
     *
     * @return array{total: float, hr: float, other: float, rows: list<array{label: string, amount: float}>}
     */
    public function spendBySource(int $year): array
    {
        $amounts = TrainingRecord::query()
            ->where('status', TrainingStatus::Approved)
            ->whereYear('date_end', $year)
            ->with('ldiTraining')
            ->get()
            ->groupBy(fn (TrainingRecord $record): string => $record->ldiTraining?->budget_source ?: self::NOT_STATED)
            ->map(fn ($group): float => (float) $group->sum(fn (TrainingRecord $record): float => (float) $record->registration_fee
                + (float) $record->tev
                + (float) $record->expenses))
            ->filter(fn (float $amount): bool => $amount > 0)
            ->all();

        arsort($amounts);

        $hr = (float) ($amounts[self::HR_SOURCE] ?? 0);
        $total = (float) array_sum($amounts);

        $rows = [];

        foreach ($amounts as $label => $amount) {
            $rows[] = ['label' => (string) $label, 'amount' => $amount];
        }

        return [
            'total' => $total,
            'hr' => $hr,
            'other' => $total - $hr,
            'rows' => $rows,
        ];
    }

    /**
     * Largest first, as a list the view can walk.
     *
     * @param  array<string, int>  $counts
     * @return list<array{label: string, count: int}>
     */
    private function rank(array $counts): array
    {
        arsort($counts);

        $rows = [];

        foreach ($counts as $label => $count) {
            $rows[] = ['label' => (string) $label, 'count' => $count];
        }

        return $rows;
    }
}
