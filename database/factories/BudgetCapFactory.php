<?php

namespace Database\Factories;

use App\Models\BudgetCap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetCap>
 */
class BudgetCapFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'year' => now()->year,
            'budget_source' => 'Human Resource',
            'amount' => 300000,
        ];
    }
}
