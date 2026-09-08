<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PersonalDataSheet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonalDataSheet>
 */
class PersonalDataSheetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-22 years'),
            'place_of_birth' => fake()->city(),
            'civil_status' => fake()->randomElement(['Single', 'Married']),
            'citizenship' => 'Filipino',
            'height_m' => fake()->randomFloat(2, 1.4, 1.9),
            'weight_kg' => fake()->randomFloat(2, 45, 95),
            'blood_type' => fake()->randomElement(['A+', 'B+', 'O+', 'AB+']),
            'residential_house_block_lot' => fake()->buildingNumber(),
            'residential_barangay' => fake()->streetName(),
            'residential_city' => fake()->city(),
            'residential_province' => 'Agusan del Norte',
            'residential_zip' => fake()->postcode(),
            'permanent_house_block_lot' => fake()->buildingNumber(),
            'permanent_barangay' => fake()->streetName(),
            'permanent_city' => fake()->city(),
            'permanent_province' => 'Agusan del Norte',
            'permanent_zip' => fake()->postcode(),
            'mobile_no' => fake()->numerify('09#########'),
            'email_address' => fake()->safeEmail(),
        ];
    }
}
