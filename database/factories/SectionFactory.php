<?php

namespace Database\Factories;

use App\Models\Division;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'division_id' => Division::factory(),
            'name' => fake()->unique()->words(2, true).' Section',
            'code' => strtoupper(fake()->unique()->lexify('????')),
            'section_head_employee_id' => null,
            'is_active' => true,
        ];
    }
}
