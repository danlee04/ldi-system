<?php

namespace App\Actions\Ldna;

use App\Enums\CompetencyType;
use App\Enums\ProficiencyLevel;
use App\Models\Competency;
use Illuminate\Support\Facades\DB;

class SaveCompetency
{
    /**
     * Write one entry of the competency dictionary, along with what it
     * looks like at each of the four levels.
     *
     * Two things follow from the type rather than from the form. A
     * technical competency is asked of each position separately, so it
     * keeps no level of its own; and one that has just become core or
     * leadership lets go of the positions it was pinned to while it was
     * technical, since it is now asked of everybody another way.
     *
     * @param  array<string, mixed>  $attributes  name, description, type, requiredLevel
     * @param  array<string, string>  $indicators  one description per level, keyed by the level's value
     */
    public function handle(?Competency $competency, array $attributes, array $indicators): Competency
    {
        return DB::transaction(function () use ($competency, $attributes, $indicators): Competency {
            $type = CompetencyType::from($attributes['type']);

            $saved = Competency::updateOrCreate(
                ['id' => $competency?->getKey()],
                [
                    'name' => $attributes['name'],
                    'description' => $attributes['description'] ?: null,
                    'type' => $type,
                    'required_level' => $type->hasOwnRequiredLevel() ? $attributes['requiredLevel'] : null,
                ],
            );

            foreach (ProficiencyLevel::cases() as $level) {
                $saved->indicators()->updateOrCreate(
                    ['level' => $level->value],
                    ['description' => $indicators[$level->value]],
                );
            }

            if ($type->hasOwnRequiredLevel()) {
                $saved->positions()->detach();
            }

            return $saved;
        });
    }
}
