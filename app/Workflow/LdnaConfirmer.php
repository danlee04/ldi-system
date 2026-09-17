<?php

namespace App\Workflow;

use App\Models\Employee;
use App\Models\User;

/**
 * Who confirms a person's needs assessment.
 *
 * The rule is ApprovalRouter's for a training record — the section head,
 * else the division head, never the person themselves, never a head who
 * has left — so whoever approves somebody's training confirms what they
 * said about themselves too. When nobody is left, HR does.
 *
 * It reads the designation off the section and division already loaded
 * rather than asking the router, which looks each head up again: one query
 * per person, and the sidebar badge counts everybody HR confirms on every
 * page. LdnaConfirmerTest holds the two to the same answer.
 *
 * Memoises the active-employee set per instance, so it must never be bound
 * as a singleton.
 */
final class LdnaConfirmer
{
    /** @var array<int, true>|null */
    private ?array $activeEmployeeIds = null;

    /**
     * The id of the employee who confirms this one, or null when HR does.
     */
    public function confirmerIdFor(Employee $employee): ?int
    {
        $heads = [
            $employee->section?->section_head_employee_id,
            $employee->division?->division_head_employee_id,
        ];

        foreach ($heads as $headId) {
            if ($headId === null) {
                continue;
            }

            $headId = (int) $headId;

            if ($headId !== $employee->getKey() && $this->isActive($headId)) {
                return $headId;
            }
        }

        return null;
    }

    /**
     * Whether this account is the one that confirms this employee.
     */
    public function confirms(User $user, Employee $employee): bool
    {
        $mine = $user->employee?->getKey();

        // Nobody confirms their own, whatever else they are.
        if ($mine !== null && $mine === $employee->getKey()) {
            return false;
        }

        $confirmerId = $this->confirmerIdFor($employee);

        return $confirmerId === null ? $user->isAdminOrHr() : $confirmerId === $mine;
    }

    private function isActive(int $employeeId): bool
    {
        $this->activeEmployeeIds ??= array_fill_keys(
            Employee::query()->active()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
            true,
        );

        return isset($this->activeEmployeeIds[$employeeId]);
    }
}
