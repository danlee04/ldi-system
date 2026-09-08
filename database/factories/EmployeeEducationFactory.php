<?php

namespace Database\Factories;

use App\Enums\EducationLevel;
use App\Models\Employee;
use App\Models\EmployeeEducation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeEducation>
 */
class EmployeeEducationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $from = fake()->numberBetween(1995, 2015);

        return [
            'employee_id' => Employee::factory(),
            'level' => EducationLevel::College,
            'school_name' => fake()->company().' University',
            'degree_course' => 'Bachelor of Science in '.fake()->word(),
            'period_from' => $from,
            'period_to' => $from + 4,
            'highest_level_units' => null,
            'year_graduated' => $from + 4,
            'honors' => null,
        ];
    }
}
