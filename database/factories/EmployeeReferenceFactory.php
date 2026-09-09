<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeReference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeReference>
 */
class EmployeeReferenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'full_name' => fake()->name(),
            'address' => 'Butuan City',
            'contact' => '09171234567',
        ];
    }
}
