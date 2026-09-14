<?php

namespace Database\Factories;

use App\Enums\ProficiencyLevel;
use App\Models\Competency;
use App\Models\LdnaAssessment;
use App\Models\LdnaRating;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LdnaRating>
 */
class LdnaRatingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ldna_assessment_id' => LdnaAssessment::factory(),
            'competency_id' => Competency::factory(),
            'required_level' => ProficiencyLevel::Intermediate,
            'self_level' => null,
            'supervisor_level' => null,
            'remarks' => null,
        ];
    }
}
