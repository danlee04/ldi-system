<?php

namespace Database\Factories;

use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Division>
 */
class DivisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Division',
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'division_head_employee_id' => null,
            'is_active' => true,
        ];
    }
}
