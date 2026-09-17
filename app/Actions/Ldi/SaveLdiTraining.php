<?php

namespace App\Actions\Ldi;

use App\Enums\LdType;
use App\Models\LdiTraining;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveLdiTraining
{
    /**
     * Record an LDI plan HR drew up, and the competencies it addresses.
     *
     * HR is named as a fund only when HR actually put an amount in — the
     * rule that keeps a plan HR did not fund off HR's cap. The budget
     * itself is whatever the two funds add up to, or null when neither did.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $competencyIds
     */
    public function handle(User $by, ?LdiTraining $plan, array $attributes, array $competencyIds = []): LdiTraining
    {
        return DB::transaction(function () use ($by, $plan, $attributes, $competencyIds): LdiTraining {
            $saved = LdiTraining::updateOrCreate(
                ['id' => $plan?->getKey()],
                [
                    ...$attributes,
                    'type_of_training' => $attributes['type_of_training'] ?: null,
                    'training_communication' => $attributes['training_communication'] ?: null,
                    'ld_type_other' => $attributes['ld_type'] === LdType::Other->value ? $attributes['ld_type_other'] : null,
                    'location' => $attributes['location'] ?: null,
                    'budget_source' => $attributes['budget_amount'] === null ? null : LdiTraining::HR_SOURCE,
                    'budget' => ((float) ($attributes['budget_amount'] ?? 0) + (float) ($attributes['other_budget_amount'] ?? 0)) ?: null,
                    'other_budget_source' => $attributes['other_budget_source'] ?: null,
                    'created_by' => $by->getKey(),
                ],
            );

            $saved->competencies()->sync($competencyIds);

            return $saved;
        });
    }
}
