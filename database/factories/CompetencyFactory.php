<?php

namespace Database\Factories;

use App\Enums\CompetencyType;
use App\Enums\ProficiencyLevel;
use App\Models\Competency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Competency>
 */
class CompetencyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'type' => CompetencyType::Core,
            'required_level' => ProficiencyLevel::Intermediate,
            'is_active' => true,
        ];
    }

    public function core(ProficiencyLevel $level = ProficiencyLevel::Intermediate): static
    {
        return $this->state(fn (): array => ['type' => CompetencyType::Core, 'required_level' => $level]);
    }

    public function leadership(ProficiencyLevel $level = ProficiencyLevel::Advanced): static
    {
        return $this->state(fn (): array => ['type' => CompetencyType::Leadership, 'required_level' => $level]);
    }

    /**
     * A technical competency takes its level from each position, never
     * from itself.
     */
    public function technical(): static
    {
        return $this->state(fn (): array => ['type' => CompetencyType::Technical, 'required_level' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * One description per level, as the dictionary gives them.
     */
    public function withIndicators(): static
    {
        return $this->afterCreating(function (Competency $competency): void {
            foreach (ProficiencyLevel::cases() as $level) {
                $competency->indicators()->create([
                    'level' => $level,
                    'description' => "{$level->label()}: ".fake()->sentence(),
                ]);
            }
        });
    }
}
