<?php

namespace Database\Factories;

use App\Enums\ApprovalLevel;
use App\Enums\LdType;
use App\Enums\TrainingStatus;
use App\Models\Employee;
use App\Models\TrainingRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<TrainingRecord>
 */
class TrainingRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::instance(fake()->dateTimeBetween('-2 years', 'now'));

        return [
            'employee_id' => Employee::factory(),
            'title' => fake()->sentence(4),
            'date_start' => $start,
            'date_end' => $start->copy()->addDays(fake()->numberBetween(0, 4)),
            'hours' => fake()->numberBetween(8, 40),
            'ld_type' => fake()->randomElement([LdType::Technical, LdType::Supervisory, LdType::Managerial, LdType::Foundation]),
            'ld_type_other' => null,
            'conducted_by' => fake()->company(),
            'location' => fake()->city(),
            'expenses' => fake()->randomFloat(2, 0, 5000),
            'registration_fee' => fake()->randomFloat(2, 0, 3000),
            'tev' => fake()->randomFloat(2, 0, 4000),
            'cpd_units' => fake()->randomFloat(1, 0, 20),
            'status' => TrainingStatus::Pending,
            'current_level' => ApprovalLevel::SectionHead,
            'submitted_by' => User::factory(),
            'rejection_reason' => null,
        ];
    }

    public function awaitingDivisionHead(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TrainingStatus::Pending,
            'current_level' => ApprovalLevel::DivisionHead,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TrainingStatus::Approved,
            'current_level' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TrainingStatus::Rejected,
            'current_level' => null,
            'rejection_reason' => fake()->sentence(),
        ]);
    }
}
