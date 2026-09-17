<?php

namespace App\Actions\Ldna;

use App\Models\Competency;
use App\Models\Position;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SetPositionCompetencies
{
    /**
     * Set the level a position is asked to reach in each technical
     * competency. A blank level means the position does not need it.
     *
     * Only the competencies handed in are touched, one at a time, rather
     * than syncing the lot: a technical competency that has since been
     * deactivated is not offered on the form, and the level it was
     * already set at must survive untouched — a past cycle's ratings were
     * copied from it.
     *
     * @param  Collection<int, Competency>  $offered  the competencies the form asked about
     * @param  array<int, string>  $levels  the level chosen for each, keyed by competency id
     */
    public function handle(Position $position, Collection $offered, array $levels): void
    {
        DB::transaction(function () use ($position, $offered, $levels): void {
            foreach ($offered as $competency) {
                $level = $levels[$competency->getKey()] ?? '';

                if ($level === '') {
                    $position->competencies()->detach($competency->getKey());

                    continue;
                }

                $position->competencies()->syncWithoutDetaching([
                    $competency->getKey() => ['required_level' => $level],
                ]);
            }
        });
    }
}
