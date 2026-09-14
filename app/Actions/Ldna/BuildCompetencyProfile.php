<?php

namespace App\Actions\Ldna;

use App\Enums\CompetencyType;
use App\Enums\ProficiencyLevel;
use App\Models\Competency;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;

/**
 * The competencies a person is assessed on, and the level each asks.
 *
 * Core is asked of everybody, Leadership of whoever is designated a head,
 * and Technical of whoever holds a position that names it. Only active
 * competencies count: a retired one belongs to the years it was used in.
 */
class BuildCompetencyProfile
{
    /**
     * @return array<int, ProficiencyLevel> the required level, keyed by competency id
     */
    public function handle(Employee $employee): array
    {
        $types = $employee->isDesignatedHead()
            ? [CompetencyType::Core, CompetencyType::Leadership]
            : [CompetencyType::Core];

        $profile = [];

        foreach (Competency::query()->active()->whereIn('type', $types)->orderBy('name')->get() as $competency) {
            // The setup form will not save one without a level; this only
            // keeps a hand-edited row from breaking a whole cycle.
            if ($competency->required_level !== null) {
                $profile[$competency->id] = $competency->required_level;
            }
        }

        if ($employee->position_id === null) {
            return $profile;
        }

        $technical = DB::table('competency_position')
            ->join('competencies', 'competencies.id', '=', 'competency_position.competency_id')
            ->where('competency_position.position_id', $employee->position_id)
            ->where('competencies.is_active', true)
            ->where('competencies.type', CompetencyType::Technical->value)
            ->orderBy('competencies.name')
            ->pluck('competency_position.required_level', 'competency_position.competency_id');

        foreach ($technical as $competencyId => $level) {
            $profile[(int) $competencyId] = ProficiencyLevel::from((string) $level);
        }

        return $profile;
    }
}
