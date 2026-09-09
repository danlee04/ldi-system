<?php

namespace App\Actions\Reports;

use App\Enums\TrainingStatus;
use App\Models\LdiTraining;
use App\Models\TrainingRecord;

/**
 * What the agency's own plans actually delivered.
 *
 * A plan is an intention until somebody attends it, so every line carries
 * both numbers: what it aimed for and who really turned up. The gap is
 * what an accomplishment report is read for.
 */
class LdiAccomplishmentReport
{
    /**
     * @return list<array{plan: LdiTraining, attendees: int, spent: float}>
     */
    public function handle(int $year, ?int $quarter = null): array
    {
        $plans = LdiTraining::query()
            ->when(
                $quarter === null,
                fn ($query) => $query->whereYear('date_start', $year),
                fn ($query) => $query->whereBetween('date_start', Quarter::bounds($year, (int) $quarter)),
            )
            ->orderBy('date_start')
            ->get();

        $rows = [];

        foreach ($plans as $plan) {
            $attended = $plan->trainingRecords()
                ->where('status', TrainingStatus::Approved)
                ->get();

            $rows[] = [
                'plan' => $plan,
                'attendees' => $attended->count(),
                'spent' => (float) $attended->sum(fn (TrainingRecord $record): float => (float) $record->registration_fee
                    + (float) $record->tev
                    + (float) $record->expenses),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{plan: LdiTraining, attendees: int, spent: float}>  $rows
     * @return list<list<string|int|float>>
     */
    public function toRows(array $rows): array
    {
        $out = [['Title', 'Inclusive dates', 'Hours', 'Facilitator', 'Target', 'Attended', 'Budget', 'Spent']];

        foreach ($rows as $row) {
            $out[] = [
                $row['plan']->title,
                $row['plan']->inclusive_dates,
                $row['plan']->hours,
                $row['plan']->facilitator,
                $row['plan']->target_attendees ?? '',
                $row['attendees'],
                $row['plan']->budget ?? '',
                $row['spent'],
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{plan: LdiTraining, attendees: int, spent: float}>  $rows
     * @return array{plans: int, attendees: int, hours: int, spent: float}
     */
    public function summarise(array $rows): array
    {
        return [
            'plans' => count($rows),
            'attendees' => (int) collect($rows)->sum('attendees'),
            'hours' => (int) collect($rows)->sum(fn (array $row): int => $row['plan']->hours),
            'spent' => (float) collect($rows)->sum('spent'),
        ];
    }
}
