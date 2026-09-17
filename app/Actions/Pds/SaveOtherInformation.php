<?php

namespace App\Actions\Pds;

use App\Enums\OtherInformationType;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class SaveOtherInformation
{
    /**
     * Write Section VIII: the special skills, the distinctions and the
     * memberships, seven printed lines in each of the three columns.
     *
     * The three are plain lists with nothing to key a line by, so they
     * are rewritten whole rather than matched line by line. That makes
     * the transaction the point of this action — between the delete and
     * the rewrite the employee has no Section VIII at all.
     *
     * @param  array<string, list<string>>  $lines  the lines typed in each column, keyed by type
     */
    public function handle(Employee $employee, array $lines): void
    {
        DB::transaction(function () use ($employee, $lines): void {
            $employee->otherInformation()->delete();

            foreach (OtherInformationType::cases() as $type) {
                foreach ($lines[$type->value] ?? [] as $description) {
                    if (blank($description)) {
                        continue;
                    }

                    $employee->otherInformation()->create([
                        'type' => $type,
                        'description' => $description,
                    ]);
                }
            }
        });

        $employee->unsetRelation('otherInformation');
    }
}
