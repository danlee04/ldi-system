<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class ImportUserAccountsFromHris extends Command
{
    protected $signature = 'ldi:import-user-accounts';

    protected $description = 'Create sign-ins for imported employees from their existing HRIS accounts';

    public function handle(): int
    {
        $sectionHeadIds = $this->toIds(Section::query()->whereNotNull('section_head_employee_id')
            ->pluck('section_head_employee_id'));

        $divisionHeadIds = $this->toIds(Division::query()->whereNotNull('division_head_employee_id')
            ->pluck('division_head_employee_id'));

        $created = 0;
        $linked = 0;
        $skipped = [];

        foreach ($this->sourceAccounts() as $row) {
            $employee = Employee::query()->firstWhere('employee_number', $row->employee_number);

            if ($employee === null) {
                $skipped[] = sprintf('%s (%s) has no local employee', $row->email, $row->employee_number);

                continue;
            }

            $role = $this->roleFor($employee, $row, $sectionHeadIds, $divisionHeadIds);
            $existing = User::query()->firstWhere('email', $row->email);

            if ($existing !== null) {
                $existing->update(['role' => $role]);
                $employee->update(['user_id' => $existing->getKey()]);
                $linked++;

                continue;
            }

            $user = new User;
            $user->forceFill([
                'name' => $employee->full_name,
                'email' => $row->email,
                'password' => $row->password,
                'role' => $role,
                'email_verified_at' => now(),
            ])->save();

            $employee->update(['user_id' => $user->getKey()]);
            $created++;
        }

        $this->info(sprintf('Created %d sign-ins and linked %d existing ones.', $created, $linked));

        foreach ($skipped as $line) {
            $this->warn($line);
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, mixed>  $ids
     * @return array<int, int>
     */
    private function toIds(Collection $ids): array
    {
        return $ids->map(fn (mixed $id): int => (int) $id)->values()->all();
    }

    /**
     * Being HR outranks being a head, because the policy grants approval
     * from the head designation rather than the role — an HR officer who
     * is also a section head still decides on their own section.
     *
     * @param  array<int, int>  $sectionHeadIds
     * @param  array<int, int>  $divisionHeadIds
     */
    private function roleFor(Employee $employee, stdClass $row, array $sectionHeadIds, array $divisionHeadIds): UserRole
    {
        if ((bool) ($row->is_hr_officer ?? false)) {
            return UserRole::Hr;
        }

        if (in_array($employee->getKey(), $divisionHeadIds, true)) {
            return UserRole::DivisionHead;
        }

        if (in_array($employee->getKey(), $sectionHeadIds, true)) {
            return UserRole::SectionHead;
        }

        return UserRole::Employee;
    }

    /**
     * @return Collection<int, stdClass>
     */
    private function sourceAccounts(): Collection
    {
        $tables = config('ldi.hris_tables', ['employees' => 'employees', 'users' => 'users']);

        return DB::connection(config('ldi.hris_connection', 'hris'))
            ->table($tables['employees'].' as e')
            ->join($tables['users'].' as u', 'u.id', '=', 'e.user_id')
            ->whereNull('e.deleted_at')
            ->get(['e.employee_number', 'e.is_hr_officer', 'u.email', 'u.password']);
    }
}
