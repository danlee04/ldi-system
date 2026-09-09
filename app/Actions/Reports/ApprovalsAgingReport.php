<?php

namespace App\Actions\Reports;

use App\Models\TrainingRecord;
use Illuminate\Support\Collection;

/**
 * What is still waiting for a decision, and how long it has waited.
 *
 * Longest wait first, because that is the one costing somebody a seat.
 * Records with no approver at all are in here too, marked as such: a
 * section with no head designated leaves them waiting on nobody, and
 * nobody would otherwise notice.
 */
class ApprovalsAgingReport
{
    /**
     * @return Collection<int, TrainingRecord>
     */
    public function handle(): Collection
    {
        return TrainingRecord::query()
            ->pending()
            ->with(['employee.division', 'employee.section'])
            ->get()
            ->sortByDesc(fn (TrainingRecord $record): int => $this->daysWaiting($record))
            ->values();
    }

    /**
     * How long since it was submitted.
     */
    public function daysWaiting(TrainingRecord $record): int
    {
        return (int) $record->created_at?->diffInDays(now());
    }

    /**
     * Who it sits with. A record with no level has nobody to decide it.
     */
    public function waitingOn(TrainingRecord $record): string
    {
        return $record->current_level?->label() ?? 'Nobody — no head designated';
    }

    /**
     * @param  Collection<int, TrainingRecord>  $records
     * @return list<list<string|int>>
     */
    public function toRows(Collection $records): array
    {
        $rows = [['Division', 'Employee', 'Training', 'Submitted', 'Days waiting', 'Waiting on']];

        foreach ($records as $record) {
            $rows[] = [
                $record->employee->division->code ?? '',
                $record->employee->listing_name ?? '',
                $record->title,
                $record->created_at?->format('d M Y') ?? '',
                $this->daysWaiting($record),
                $this->waitingOn($record),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, TrainingRecord>  $records
     * @return array{pending: int, unroutable: int, longest: int}
     */
    public function summarise(Collection $records): array
    {
        return [
            'pending' => $records->count(),
            'unroutable' => $records->whereNull('current_level')->count(),
            'longest' => (int) $records->max(fn (TrainingRecord $record): int => $this->daysWaiting($record)),
        ];
    }
}
