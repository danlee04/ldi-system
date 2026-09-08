<?php

namespace App\Actions\Reports;

use App\Enums\TrainingStatus;
use App\Models\Employee;
use App\Models\LdiTraining;

/**
 * The DOH "Training Report on Attendance to Learning and Development
 * Interventions" — one row per planned training, with participants counted
 * by category and by sex.
 *
 * One row is one plan, not one attendance: the form asks how many people
 * from each category attended each intervention.
 */
class DohLdiReport
{
    /**
     * @return list<array{
     *     plan: LdiTraining,
     *     days: int,
     *     mcc: int,
     *     dm: int,
     *     ad_staff: int,
     *     uncategorised: int,
     *     female: int,
     *     male: int,
     *     unstated: int,
     * }>
     */
    public function handle(?int $year = null, ?int $month = null, ?string $search = null): array
    {
        $plans = LdiTraining::query()
            ->when($year !== null, fn ($query) => $query->whereYear('date_start', $year))
            ->when($month !== null, fn ($query) => $query->whereMonth('date_start', $month))
            ->when($search !== null && $search !== '', function ($query) use ($search): void {
                $term = '%'.$search.'%';

                $query->where(fn ($match) => $match->where('title', 'like', $term)
                    ->orWhere('development_partner', 'like', $term));
            })
            ->with(['trainingRecords' => fn ($query) => $query->where('status', TrainingStatus::Approved)])
            ->orderBy('date_start')
            ->get();

        $mccPositions = config('ldi.doh.mcc_positions', []);
        $dmDivisions = config('ldi.doh.dm_division_codes', []);
        $adDivisions = config('ldi.doh.ad_staff_division_codes', []);

        $rows = [];

        foreach ($plans as $plan) {
            $attendees = Employee::query()
                ->whereIn('id', $plan->trainingRecords->pluck('employee_id')->unique())
                ->with(['position', 'division'])
                ->get();

            $mcc = $attendees->filter(
                fn (Employee $employee): bool => in_array($employee->position?->title, $mccPositions, true),
            );

            // A doctor is counted once, as MCC, even though they also sit in
            // a division — otherwise the categories would double count.
            $rest = $attendees->diff($mcc);

            $dm = $rest->filter(fn (Employee $e): bool => in_array($e->division?->code, $dmDivisions, true));
            $adStaff = $rest->filter(fn (Employee $e): bool => in_array($e->division?->code, $adDivisions, true));

            $rows[] = [
                'plan' => $plan,
                'days' => (int) $plan->date_start->diffInDays($plan->date_end) + 1,
                'mcc' => $mcc->count(),
                'dm' => $dm->count(),
                'ad_staff' => $adStaff->count(),
                'uncategorised' => $rest->count() - $dm->count() - $adStaff->count(),
                'female' => $attendees->where('gender', 'Female')->count(),
                'male' => $attendees->where('gender', 'Male')->count(),
                'unstated' => $attendees->whereNull('gender')->count(),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{plan: LdiTraining, days: int, mcc: int, dm: int, ad_staff: int, uncategorised: int, female: int, male: int, unstated: int}>  $rows
     * @return array{mcc: int, dm: int, ad_staff: int, uncategorised: int, female: int, male: int, unstated: int, hours: int, budget: float}
     */
    public function summarise(array $rows): array
    {
        $sum = fn (string $key): int => (int) collect($rows)->sum($key);

        return [
            'mcc' => $sum('mcc'),
            'dm' => $sum('dm'),
            'ad_staff' => $sum('ad_staff'),
            'uncategorised' => $sum('uncategorised'),
            'female' => $sum('female'),
            'male' => $sum('male'),
            'unstated' => $sum('unstated'),
            'hours' => (int) collect($rows)->sum(fn (array $row): int => $row['plan']->hours),
            'budget' => (float) collect($rows)->sum(fn (array $row): float => (float) $row['plan']->budget),
        ];
    }
}
