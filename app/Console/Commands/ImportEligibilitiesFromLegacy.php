<?php

namespace App\Console\Commands;

use App\Models\Eligibility;
use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ImportEligibilitiesFromLegacy extends Command
{
    protected $signature = 'ldi:import-eligibilities';

    protected $description = 'Copy civil service eligibility from the legacy hr_training_system';

    public function handle(): int
    {
        $names = $this->importEligibilityNames();

        [$matched, $unmatched] = $this->importEmployeeEligibility($names);

        $this->info(sprintf(
            'Imported %d eligibility types and matched %d employees.',
            count($names),
            $matched,
        ));

        foreach ($unmatched as $name) {
            $this->warn(sprintf('No local employee matched "%s".', $name));
        }

        return self::SUCCESS;
    }

    /**
     * Source table names, overridable so tests can point at fixtures.
     *
     * @return array<string, string>
     */
    private function sourceTables(): array
    {
        return config('ldi.legacy_tables', [
            'eligibilities' => 'eligibilities',
            'employees' => 'employees',
        ]);
    }

    private function source(string $entity): Builder
    {
        return DB::connection(config('ldi.legacy_connection', 'legacy'))
            ->table($this->sourceTables()[$entity]);
    }

    /**
     * @return array<int, int> source eligibility id to local id
     */
    private function importEligibilityNames(): array
    {
        $map = [];

        foreach ($this->source('eligibilities')->get() as $row) {
            $eligibility = Eligibility::updateOrCreate(['name' => $row->eligibility_name]);

            $map[$row->id] = $eligibility->id;
        }

        return $map;
    }

    /**
     * The legacy table has no employee number, so people are matched on
     * name. Suffixes live inside the first name there but in their own
     * column here, so both sides are stripped of them before comparing.
     *
     * @param  array<int, int>  $eligibilityIds
     * @return array{int, list<string>}
     */
    private function importEmployeeEligibility(array $eligibilityIds): array
    {
        $local = Employee::query()
            ->get()
            ->keyBy(fn (Employee $employee): string => $this->matchKey($employee->first_name, $employee->last_name));

        $matched = 0;
        $unmatched = [];

        foreach ($this->source('employees')->get() as $row) {
            $key = $this->matchKey($row->firstname, $row->lastname);
            $employee = $local->get($key);

            if ($employee === null) {
                $unmatched[] = trim($row->firstname.' '.$row->lastname);

                continue;
            }

            $employee->update([
                'eligibility_id' => $eligibilityIds[$row->eligibility_id] ?? null,
                'eligibility_detail' => $row->eligibility ?: null,
                'eligibility_expires_on' => $row->expiry_date ?: null,
            ]);

            $matched++;
        }

        return [$matched, $unmatched];
    }

    /**
     * Letters only, upper case, with any name suffix removed.
     */
    private function matchKey(?string $firstName, ?string $lastName): string
    {
        $name = strtoupper($firstName.' '.$lastName);
        $name = (string) preg_replace('/[^A-Z ]/', ' ', $name);
        $name = (string) preg_replace('/\b(JR|SR|II|III|IV)\b/', '', $name);

        return (string) preg_replace('/\s+/', '', $name);
    }
}
