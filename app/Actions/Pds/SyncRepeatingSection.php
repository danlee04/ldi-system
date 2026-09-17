<?php

namespace App\Actions\Pds;

use App\Models\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SyncRepeatingSection
{
    /**
     * Write the lines of one repeating PDS section — eligibilities, work,
     * children, voluntary work, references — and drop the ones that are
     * no longer on the form.
     *
     * Taking a line off the form is how it is deleted, so the write and
     * the delete are one transaction: half a saved section would leave
     * the employee looking at lines that are no longer theirs.
     *
     * Each line is looked up through the employee's own relation, so an
     * id tampered with in the browser finds nothing and starts a new line
     * instead of editing somebody else's.
     *
     * The relation name comes from the page's own map of sections, never
     * from the request.
     *
     * @param  Collection<int, array<string, mixed>>  $rows  keyed by their index on the form
     * @param  array<int, int|null>  $ids  the record each index stands for, keyed the same way
     * @return array<int, int> the record now behind each index
     */
    public function handle(Employee $employee, string $relation, Collection $rows, array $ids): array
    {
        return DB::transaction(function () use ($employee, $relation, $rows, $ids): array {
            $saved = [];

            foreach ($rows as $index => $row) {
                $values = collect($row)
                    ->map(fn (mixed $value): mixed => $value === '' ? null : $value)
                    ->except('id')
                    ->all();

                $record = $employee->{$relation}()->findOrNew($ids[$index] ?? 0);

                $record->fill($values)->save();

                $saved[$index] = $record->getKey();
            }

            $employee->{$relation}()->reorder()->whereNotIn('id', $saved)->delete();
            $employee->unsetRelation($relation);

            return $saved;
        });
    }
}
