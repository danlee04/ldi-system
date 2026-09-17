<?php

namespace App\Actions\Ldna;

use App\Models\Competency;

class DeleteCompetency
{
    /**
     * Take a competency out of the dictionary altogether.
     *
     * Refused once somebody has been assessed on it: the ratings would go
     * with it and a past cycle would lose its answers. Such a competency
     * is deactivated instead, which leaves it out of the next cycle
     * without touching the ones already run.
     *
     * @return bool whether it was deleted
     */
    public function handle(Competency $competency): bool
    {
        if ($competency->isInUse()) {
            return false;
        }

        $competency->delete();

        return true;
    }
}
