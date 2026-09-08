<?php

namespace App\Models;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use Database\Factories\TrainingApprovalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingApproval extends Model
{
    /** @use HasFactory<TrainingApprovalFactory> */
    use HasFactory;

    protected $fillable = [
        'training_record_id',
        'level',
        'approver_user_id',
        'decision',
        'remarks',
        'decided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => ApprovalLevel::class,
            'decision' => ApprovalDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TrainingRecord, $this>
     */
    public function trainingRecord(): BelongsTo
    {
        return $this->belongsTo(TrainingRecord::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
