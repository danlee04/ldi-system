<?php

namespace App\Actions\Ldi;

use App\Models\LdiTraining;

class DeleteLdiTraining
{
    /**
     * Take a plan out altogether — one drawn up by mistake, or one that
     * never ran.
     *
     * Refused while anybody is recorded as attending it. Their records
     * would survive, but cut loose from the plan, and so drop out of the
     * DOH LDI report without a word. Removing the attendees first makes
     * each of those losses a decision somebody made.
     *
     * @return bool whether it was deleted
     */
    public function handle(LdiTraining $plan): bool
    {
        if ($plan->trainingRecords()->exists()) {
            return false;
        }

        $plan->delete();

        return true;
    }
}
