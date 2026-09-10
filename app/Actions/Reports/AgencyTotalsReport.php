<?php

namespace App\Actions\Reports;

use App\Models\Employee;
use App\Models\LdiTraining;

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
     * What each fund put into the year's plans.
     *
     * This is funding, not spending. A plan can cost 19,000 while HR's
     * budget carries only the 6,000 registration, so the answer to "what
     * did HR fund" is the amount the plan states — never the plan's whole
     * cost, and never a share worked out from one.
     *
     * The agency's own budget is separated from everything else, because
     * that is the split the office is asked about. The funds beyond it are
     * not named here — they are one figure on the card, and the LDI plans
     * are where a particular fund is looked up.
     *
     * @return array{total: float, hr: float, other: float}
     */
    public function fundingBySource(int $year): array
    {
        $hr = 0.0;
        $total = 0.0;

        foreach (LdiTraining::query()->whereYear('date_start', $year)->get() as $plan) {
            foreach ([$plan->budget_source, $plan->other_budget_source] as $source) {
                if (blank($source)) {
                    continue;
                }

                $funded = $plan->fundedBy($source);
                $total += $funded;

                if ($source === self::HR_SOURCE) {
                    $hr += $funded;
                }
            }
        }

        return [
            'total' => $total,
            'hr' => $hr,
            'other' => $total - $hr,
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
