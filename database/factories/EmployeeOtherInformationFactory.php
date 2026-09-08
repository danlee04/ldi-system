<?php

namespace Database\Factories;

use App\Enums\OtherInformationType;
use App\Models\Employee;
use App\Models\EmployeeOtherInformation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeOtherInformation>
 */
class EmployeeOtherInformationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'type' => OtherInformationType::Skill,
            'description' => 'Photography',
        ];
    }
}
