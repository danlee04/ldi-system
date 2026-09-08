<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeVoluntaryWork;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeVoluntaryWork>
 */
class EmployeeVoluntaryWorkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'organization' => 'Philippine Red Cross, Butuan City Chapter',
            'from_date' => fake()->dateTimeBetween('-8 years', '-3 years'),
            'to_date' => fake()->dateTimeBetween('-2 years', '-1 year'),
            'hours' => fake()->numberBetween(8, 200),
            'position' => 'Volunteer',
        ];
    }
}
