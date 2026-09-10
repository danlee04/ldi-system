<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::instance(fake()->dateTimeBetween('-2 months', '+2 months'));

        return [
            'title' => fake()->sentence(4),
            'type' => fake()->randomElement(ActivityType::cases()),
            'date_start' => $start,
            'date_end' => $start->copy()->addDays(fake()->numberBetween(0, 2)),
            'time_start' => null,
            'time_end' => null,
            'location' => fake()->city(),
            'description' => null,
            'created_by' => User::factory()->hr(),
        ];
    }

    /**
     * One that runs for part of a day rather than all of it.
     */
    public function timed(string $from = '09:00', string $until = '11:00'): static
    {
        return $this->state(fn (): array => ['time_start' => $from, 'time_end' => $until]);
    }

    public function on(string $date): static
    {
        return $this->state(fn (): array => ['date_start' => $date, 'date_end' => $date]);
    }
}
