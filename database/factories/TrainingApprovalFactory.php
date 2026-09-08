<?php

namespace Database\Factories;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use App\Models\TrainingApproval;
use App\Models\TrainingRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingApproval>
 */
class TrainingApprovalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'training_record_id' => TrainingRecord::factory(),
            'level' => ApprovalLevel::SectionHead,
            'approver_user_id' => User::factory(),
            'decision' => ApprovalDecision::Approved,
            'remarks' => null,
            'decided_at' => now(),
        ];
    }
}
