<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeChild;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeChild>
 */
class EmployeeChildFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'full_name' => fake()->name(),
            'date_of_birth' => fake()->dateTimeBetween('-25 years', '-1 year'),
        ];
    }
}
