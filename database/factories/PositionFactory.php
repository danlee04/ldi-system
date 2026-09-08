<?php

namespace Database\Factories;

use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->jobTitle(),
            'item_number' => fake()->bothify('ITEM-####'),
            'salary_grade' => fake()->numberBetween(1, 33),
            'is_active' => true,
        ];
    }
}
