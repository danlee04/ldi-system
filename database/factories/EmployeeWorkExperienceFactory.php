<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeWorkExperience;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeWorkExperience>
 */
class EmployeeWorkExperienceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'from_date' => fake()->dateTimeBetween('-15 years', '-5 years'),
            'to_date' => fake()->dateTimeBetween('-4 years', '-1 year'),
            'position_title' => 'Administrative Assistant II',
            'agency_name' => 'Department of Health',
            'monthly_salary' => fake()->numberBetween(15000, 60000),
            'salary_grade' => '11-1',
            'appointment_status' => 'Permanent',
            'is_government' => true,
        ];
    }

    /**
     * A post they still hold.
     */
    public function current(): static
    {
        return $this->state(['to_date' => null]);
    }
}
