<?php

namespace App\Workflow;

use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The people a head is responsible for.
 *
 * Read from the designation — who is named head of a division or section —
 * rather than from the account's role, exactly as ApprovalRouter reads it.
 * A role left behind after a head changes would otherwise show somebody a
 * team the approvals page no longer sends them.
 */
final class HeadedTeam
{
    /**
     * @param  list<int>  $divisionIds
     * @param  list<int>  $sectionIds
     */
    private function __construct(
        public readonly array $divisionIds,
        public readonly array $sectionIds,
        public readonly string $name,
        /** The division a section head's sections sit in; null when the team is itself a division. */
        public readonly ?string $divisionName,
    ) {}

    /**
     * The team a person heads, or null when they head nothing.
     */
    public static function for(User $user): ?self
    {
        $employee = $user->employee;

        if (! $employee instanceof Employee) {
            return null;
        }

        $divisions = Division::query()->where('division_head_employee_id', $employee->getKey())->orderBy('name')->get();
        $sections = Section::query()->where('section_head_employee_id', $employee->getKey())->orderBy('name')->get();

        if ($divisions->isEmpty() && $sections->isEmpty()) {
            return null;
        }

        return new self(
            array_values(array_map('intval', $divisions->modelKeys())),
            array_values(array_map('intval', $sections->modelKeys())),
            // A division says more than the sections inside it, so it is
            // the name a division head's team goes by.
            $divisions->isNotEmpty() ? $divisions->pluck('name')->join(', ') : $sections->pluck('name')->join(', '),
            // A section on its own does not say where in the Center it
            // sits, so a section head is told the division too. A division
            // head needs no such line: their team is the division.
            $divisions->isNotEmpty()
                ? null
                : ($sections->load('division')->pluck('division.name')->filter()->unique()->join(', ') ?: null),
        );
    }

    /**
     * Whether this is a division's worth of people, broken down by section,
     * or a section's, broken down by person.
     */
    public function headsDivision(): bool
    {
        return $this->divisionIds !== [];
    }

    /**
     * Everybody on the team who still works here.
     *
     * @return Builder<Employee>
     */
    public function employees(): Builder
    {
        return Employee::query()
            ->active()
            ->where(fn (Builder $query) => $query
                ->whereIn('division_id', $this->divisionIds)
                ->orWhereIn('section_id', $this->sectionIds));
    }
}
