<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LdnaAssessment;
use App\Models\LdnaCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LdnaAssessment>
 */
class LdnaAssessmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ldna_cycle_id' => LdnaCycle::factory(),
            'employee_id' => Employee::factory(),
            'position_id' => null,
            'self_submitted_at' => null,
            'rated_at' => null,
            'rated_by' => null,
        ];
    }
}
