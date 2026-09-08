<?php

namespace Database\Factories;

use App\Models\Eligibility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Eligibility>
 */
class EligibilityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Eligibility',
        ];
    }
}
