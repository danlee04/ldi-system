<?php

namespace App\Console\Commands;

use App\Models\TrainingRecord;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Undoes the double counting the history import left behind.
 *
 * `hr_training_system.trainings.expenses` is the whole cost of attending,
 * not a third category: in every one of its 1,002 rows it equals
 * registration_fee + tev. The import copied it into this app's "Other
 * expenses", which is a genuine third figure, so every total since has
 * counted the same money twice.
 *
 * Only a record whose amounts still match a legacy row exactly is
 * touched, so a figure somebody typed in this app is left alone.
 */
class FixImportedTrainingExpenses extends Command
{
    protected $signature = 'ldi:fix-imported-expenses {--apply : Write the correction instead of only reporting it}';

    protected $description = 'Clear the duplicated Other expenses the legacy history import wrote';

    public function handle(): int
    {
        $fromLegacy = $this->legacyCostFingerprints();

        if ($fromLegacy === []) {
            $this->info('The legacy history holds no duplicated cost. Nothing to correct.');

            return self::SUCCESS;
        }

        $doubled = TrainingRecord::query()
            ->whereNotNull('expenses')
            ->where('expenses', '>', 0)
            ->get()
            ->filter(fn (TrainingRecord $record): bool => $this->duplicatesItsOwnTotal($record));

        $imported = $doubled->filter(fn (TrainingRecord $record): bool => isset($fromLegacy[$this->fingerprint(
            $record->title,
            $record->date_start->toDateString(),
            (float) $record->registration_fee,
            (float) $record->tev,
            (float) $record->expenses,
        )]));

        $typedHere = $doubled->count() - $imported->count();

        if ($imported->isEmpty()) {
            $this->info('No imported record still carries a duplicated cost.');
        } else {
            $this->reportTotals($imported);
        }

        if ($typedHere > 0) {
            $this->warn(sprintf(
                '%d other record(s) have Other expenses equal to registration + travel but do not come from the legacy history. They were typed in this app, so they are left alone.',
                $typedHere,
            ));
        }

        if ($imported->isEmpty()) {
            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->comment('Nothing was written. Run it again with --apply to make the correction.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($imported): void {
            TrainingRecord::whereKey($imported->modelKeys())->update(['expenses' => null]);
        });

        $this->info(sprintf('Cleared Other expenses on %d record(s).', $imported->count()));

        return self::SUCCESS;
    }

    /**
     * Says what the correction is worth, so it can be checked against the
     * figure on the reports before anything is written.
     *
     * @param  Collection<int, TrainingRecord>  $records
     */
    private function reportTotals(Collection $records): void
    {
        $duplicated = $records->sum(fn (TrainingRecord $record): float => (float) $record->expenses);
        $before = $records->sum(fn (TrainingRecord $record): float => $this->totalCost($record));

        $this->info(sprintf('%d imported record(s) count the same money twice.', $records->count()));

        $this->table(['', 'Amount'], [
            ['Counted now', number_format($before, 2)],
            ['Counted twice', number_format($duplicated, 2)],
            ['Correct total', number_format($before - $duplicated, 2)],
        ]);
    }

    /**
     * The amounts of every legacy row whose `expenses` is the total of the
     * other two, keyed so a local record can be recognised as its copy.
     *
     * @return array<string, true>
     */
    private function legacyCostFingerprints(): array
    {
        $fingerprints = [];

        foreach ($this->legacyTrainings()->get() as $row) {
            $expenses = (float) $row->expenses;
            $parts = (float) $row->registration_fee + (float) $row->tev;

            if ($expenses <= 0 || abs($expenses - $parts) >= 0.01) {
                continue;
            }

            $fingerprints[$this->fingerprint(
                (string) $row->training_title,
                substr((string) $row->date_start, 0, 10),
                (float) $row->registration_fee,
                (float) $row->tev,
                $expenses,
            )] = true;
        }

        return $fingerprints;
    }

    private function legacyTrainings(): Builder
    {
        $tables = config('ldi.legacy_tables', ['trainings' => 'trainings']);

        return DB::connection(config('ldi.legacy_connection', 'legacy'))->table($tables['trainings']);
    }

    private function fingerprint(string $title, string $start, float $registration, float $tev, float $expenses): string
    {
        return sprintf('%s|%s|%.2f|%.2f|%.2f', trim($title), $start, $registration, $tev, $expenses);
    }

    private function duplicatesItsOwnTotal(TrainingRecord $record): bool
    {
        $parts = (float) $record->registration_fee + (float) $record->tev;

        return abs((float) $record->expenses - $parts) < 0.01;
    }

    private function totalCost(TrainingRecord $record): float
    {
        return (float) $record->registration_fee + (float) $record->tev + (float) $record->expenses;
    }
}
