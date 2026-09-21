<?php

namespace App\Actions\Ldna;

use App\Models\LdnaCycle;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

class DeleteLdnaCycle
{
    /**
     * Take a cycle out, with every assessment and answer in it — for one
     * set up by mistake, or filled with test data.
     *
     * Once a head has confirmed anybody, the cycle is planning material
     * and HR must type its year to go ahead. A slip of the mouse should
     * not be able to erase a whole office's self-assessments.
     *
     * The notices it sent go with it, or everybody's bell would keep
     * announcing a cycle that is no longer there.
     *
     * @return bool whether it was deleted
     */
    public function handle(LdnaCycle $cycle, ?string $typedYear = null): bool
    {
        if ($this->needsTypedYear($cycle) && trim((string) $typedYear) !== (string) $cycle->year) {
            return false;
        }

        DB::transaction(function () use ($cycle): void {
            DatabaseNotification::query()
                ->where('data->ldna_cycle_id', $cycle->getKey())
                ->orWhereIn('data->ldna_assessment_id', $cycle->assessments()->pluck('id'))
                ->delete();

            // Assessments and their ratings cascade in the database.
            $cycle->delete();
        });

        return true;
    }

    public function needsTypedYear(LdnaCycle $cycle): bool
    {
        return $cycle->assessments()->whereNotNull('confirmed_at')->exists();
    }
}
