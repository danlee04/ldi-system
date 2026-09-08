<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeEligibility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeEligibility>
 */
class EmployeeEligibilityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'eligibility_id' => null,
            'detail' => 'CSP - Career Service Professional',
            'rating' => (string) fake()->numberBetween(80, 95),
            'date_of_examination' => fake()->dateTimeBetween('-15 years', '-2 years'),
            'place_of_examination' => fake()->city(),
            'license_number' => null,
            'date_of_validity' => null,
        ];
    }
}
