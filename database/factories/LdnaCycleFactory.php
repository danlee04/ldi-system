<?php

namespace Database\Factories;

use App\Models\LdnaCycle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LdnaCycle>
 */
class LdnaCycleFactory extends Factory
{
    /**
     * An open one: the window takes in today.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'year' => fake()->unique()->numberBetween(2027, 2099),
            'opens_on' => today()->subDay(),
            'closes_on' => today()->addMonth(),
            'created_by' => User::factory()->hr(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['opens_on' => today()->subMonths(2), 'closes_on' => today()->subDay()]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (): array => ['opens_on' => today()->addWeek(), 'closes_on' => today()->addMonth()]);
    }
}
