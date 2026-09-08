<?php

namespace App\Workflow;

use App\Enums\ApprovalLevel;
use App\Models\Employee;

class ApprovalRouter
{
    /**
     * The level that should act first on a record for this employee.
     *
     * Null means nobody can approve it: neither the section nor the
     * division has an available head. The record stays pending and
     * surfaces on the HR "no approver" list.
     */
    public function firstLevelFor(Employee $employee): ?ApprovalLevel
    {
        if ($this->approverFor(ApprovalLevel::SectionHead, $employee) instanceof Employee) {
            return ApprovalLevel::SectionHead;
        }

        if ($this->approverFor(ApprovalLevel::DivisionHead, $employee) instanceof Employee) {
            return ApprovalLevel::DivisionHead;
        }

        return null;
    }

    /**
     * The level that follows the given one.
     *
     * Null means the record is fully approved. This differs from
     * firstLevelFor() returning null, which means unroutable — the two
     * are told apart by the caller, not by this value.
     */
    public function levelAfter(ApprovalLevel $level, Employee $employee): ?ApprovalLevel
    {
        if ($level === ApprovalLevel::DivisionHead) {
            return null;
        }

        return $this->approverFor(ApprovalLevel::DivisionHead, $employee) instanceof Employee
            ? ApprovalLevel::DivisionHead
            : null;
    }

    /**
     * The employee designated to decide at the given level, if there is one.
     *
     * Nobody may approve their own record, and an inactive head does not count.
     */
    public function approverFor(ApprovalLevel $level, Employee $employee): ?Employee
    {
        $headId = match ($level) {
            ApprovalLevel::SectionHead => $employee->section?->section_head_employee_id,
            ApprovalLevel::DivisionHead => $employee->division?->division_head_employee_id,
        };

        if ($headId === null || $headId === $employee->getKey()) {
            return null;
        }

        return Employee::query()->active()->find($headId);
    }
}
