<?php

namespace App\Console\Commands;

use App\Enums\LdType;
use App\Enums\TrainingStatus;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\LdiTraining;
use App\Models\TrainingRecord;
use App\Models\User;
use App\Workflow\ApprovalRouter;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * One-off migration of the history held by hr_training_system.
 *
 * Two kinds of row live in its `trainings` table: rows with an employee,
 * which are attendance, and 28 rows with none, which are the shells the
 * `ldi_training` planning data hangs off. They are imported as different
 * things here.
 */
class ImportTrainingHistoryFromLegacy extends Command
{
    protected $signature = 'ldi:import-training-history';

    protected $description = 'Copy the planned trainings and the attendance history out of hr_training_system';

    public function handle(ApprovalRouter $router): int
    {
        if (Employee::query()->doesntExist()) {
            $this->error('There are no employees yet. Import the roster before the history.');

            return self::FAILURE;
        }

        $employees = $this->localEmployeesByLegacyId();

        $plans = $this->importPlans();
        [$attendance, $skipped, $withoutLdType] = $this->importAttendance($employees, $router);

        $this->info(sprintf('Imported %d planned trainings and %d attendance records.', $plans, $attendance));

        if ($withoutLdType > 0) {
            $this->warn(sprintf('%d records had no usable type of LD and need one set by hand.', $withoutLdType));
        }

        foreach ($skipped as $line) {
            $this->warn($line);
        }

        return self::SUCCESS;
    }

    /**
     * Source tables are named through config so tests can point the whole
     * importer at fixtures instead of a real database.
     */
    private function legacy(string $entity): Builder
    {
        $tables = config('ldi.legacy_tables', [
            'employees' => 'employees',
            'trainings' => 'trainings',
            'ldi_training' => 'ldi_training',
        ]);

        return DB::connection(config('ldi.legacy_connection', 'legacy'))->table($tables[$entity]);
    }

    /**
     * Legacy employee id to the local employee, matched on name because the
     * legacy roster carries no employee number.
     *
     * @return Collection<int, Employee>
     */
    private function localEmployeesByLegacyId(): Collection
    {
        $local = Employee::query()->with('user')->get()
            ->keyBy(fn (Employee $employee): string => $this->matchKey($employee->first_name, $employee->last_name));

        return $this->legacy('employees')->get()
            ->mapWithKeys(fn (stdClass $row): array => [
                $row->employee_id => $local->get($this->matchKey($row->firstname, $row->lastname)),
            ])
            ->filter();
    }

    /**
     * The 28 planning rows, each joined to the employee-less training row
     * that holds its dates, hours and facilitator.
     */
    private function importPlans(): int
    {
        $creator = User::query()->where('role', UserRole::Hr)->first()
            ?? User::query()->where('role', UserRole::Admin)->firstOrFail();

        $imported = 0;

        foreach ($this->legacy('ldi_training')->get() as $row) {
            $shell = $this->legacy('trainings')->where('training_id', $row->training_id)->first();

            if ($shell === null) {
                continue;
            }

            [$ldType, $ldTypeOther] = $this->mapLdType($shell->type_of_ld);

            $plan = LdiTraining::query()
                ->where('title', $row->training_title)
                ->whereDate('date_start', $shell->date_start)
                ->first() ?? new LdiTraining;

            $plan->fill([
                'title' => $row->training_title,
                'date_start' => $shell->date_start,
                'development_partner' => $row->development_partner ?: 'Not stated',
                'facilitator' => $shell->facilitator ?: ($row->development_partner ?: 'Not stated'),
                'type_of_training' => $row->type_of_training ?: null,
                'training_communication' => $shell->training_communication ?: null,
                'date_end' => $shell->date_end,
                'hours' => (int) $shell->training_hours,
                'cpd_units' => $shell->cpd_units !== null ? (float) $shell->cpd_units : null,
                'ld_type' => $ldType,
                'ld_type_other' => $ldTypeOther,
                'location' => $shell->location ?: null,
                'target_attendees' => $row->target_attendees !== null && $row->target_attendees !== ''
                    ? (int) $row->target_attendees
                    : null,
                'budget' => $row->budget,
                'budget_source' => $row->budget_source ?: null,
                'created_by' => $creator->getKey(),
            ])->save();

            $imported++;
        }

        return $imported;
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return array{int, list<string>, int}
     */
    private function importAttendance(Collection $employees, ApprovalRouter $router): array
    {
        $imported = 0;
        $skipped = [];
        $withoutLdType = 0;

        foreach ($this->legacy('trainings')->whereNotNull('employee_id')->get() as $row) {
            $employee = $employees->get($row->employee_id);

            if ($employee === null) {
                $skipped[] = sprintf('Training %s belongs to an employee with no local match.', $row->training_id);

                continue;
            }

            [$ldType, $ldTypeOther] = $this->mapLdType($row->type_of_ld);

            if ($ldType === LdType::Other && $ldTypeOther === null) {
                $withoutLdType++;
            }

            $status = $row->status === 'pending' ? TrainingStatus::Pending : TrainingStatus::Approved;

            $record = TrainingRecord::query()
                ->where('employee_id', $employee->getKey())
                ->where('title', $row->training_title)
                ->whereDate('date_start', $row->date_start)
                ->first() ?? new TrainingRecord;

            $record->fill([
                'employee_id' => $employee->getKey(),
                'title' => $row->training_title,
                'date_start' => $row->date_start,
                'date_end' => $row->date_end,
                'hours' => (int) $row->training_hours,
                'ld_type' => $ldType,
                'ld_type_other' => $ldTypeOther,
                'conducted_by' => $row->facilitator ?: 'Not stated',
                'location' => $row->location ?: null,
                'expenses' => $row->expenses,
                'registration_fee' => $row->registration_fee,
                'tev' => $row->tev,
                'cpd_units' => $row->cpd_units !== null ? (float) $row->cpd_units : null,
                'status' => $status,
                'current_level' => $status === TrainingStatus::Pending
                    ? $router->firstLevelFor($employee)
                    : null,
                'submitted_by' => $employee->user?->getKey() ?? $this->fallbackSubmitter(),
                'rejection_reason' => $row->rejection_reason ?: null,
            ])->save();

            $imported++;
        }

        return [$imported, $skipped, $withoutLdType];
    }

    private function fallbackSubmitter(): int
    {
        return User::query()->where('role', UserRole::Hr)->value('id')
            ?? User::query()->where('role', UserRole::Admin)->value('id');
    }

    /**
     * The legacy column is free text. The four CS Form 212 types map
     * straight across; anything else becomes Other carrying its own words,
     * which is exactly what the Other case is for.
     *
     * @return array{LdType, string|null}
     */
    private function mapLdType(?string $value): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return [LdType::Other, null];
        }

        foreach (LdType::cases() as $case) {
            if ($case !== LdType::Other && strcasecmp($case->name, $value) === 0) {
                return [$case, null];
            }
        }

        return [LdType::Other, $value];
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
