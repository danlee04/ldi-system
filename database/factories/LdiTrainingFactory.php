<?php

namespace Database\Factories;

use App\Enums\LdType;
use App\Models\LdiTraining;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<LdiTraining>
 */
class LdiTrainingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::instance(fake()->dateTimeBetween('-1 year', '+3 months'));

        return [
            'title' => fake()->sentence(4),
            'development_partner' => fake()->company(),
            'type_of_training' => fake()->randomElement(['Training', 'Workshop', 'Seminar', 'Convention']),
            'date_start' => $start,
            'date_end' => $start->copy()->addDays(fake()->numberBetween(0, 4)),
            'hours' => fake()->numberBetween(8, 40),
            'ld_type' => fake()->randomElement([LdType::Technical, LdType::Supervisory, LdType::Managerial, LdType::Foundation]),
            'ld_type_other' => null,
            'location' => fake()->city(),
            'target_attendees' => fake()->numberBetween(5, 30),
            'budget' => fake()->randomFloat(2, 5000, 100000),
            'budget_source' => fake()->randomElement(['WFP-GAA 2026', 'Human Resource']),
            'created_by' => User::factory()->hr(),
        ];
    }
}
