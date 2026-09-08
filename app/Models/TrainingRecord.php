<?php

namespace App\Models;

use App\Enums\ApprovalLevel;
use App\Enums\LdType;
use App\Enums\TrainingStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TrainingRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $employee_id
 * @property int|null $ldi_training_id
 * @property string $title
 * @property CarbonImmutable $date_start
 * @property CarbonImmutable $date_end
 * @property int $hours
 * @property LdType $ld_type
 * @property string|null $ld_type_other
 * @property string $conducted_by
 * @property string|null $location
 * @property string|null $expenses
 * @property string|null $registration_fee
 * @property string|null $tev
 * @property float|null $cpd_units
 * @property TrainingStatus $status
 * @property ApprovalLevel|null $current_level
 * @property int $submitted_by
 * @property string|null $rejection_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class TrainingRecord extends Model
{
    /** @use HasFactory<TrainingRecordFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'ldi_training_id',
        'title',
        'date_start',
        'date_end',
        'hours',
        'ld_type',
        'ld_type_other',
        'conducted_by',
        'location',
        'expenses',
        'registration_fee',
        'tev',
        'cpd_units',
        'status',
        'current_level',
        'submitted_by',
        'rejection_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_start' => 'date',
            'date_end' => 'date',
            'hours' => 'integer',
            'ld_type' => LdType::class,
            'status' => TrainingStatus::class,
            'current_level' => ApprovalLevel::class,
            'expenses' => 'decimal:2',
            'registration_fee' => 'decimal:2',
            'tev' => 'decimal:2',
            'cpd_units' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The agency plan this attendance belongs to, if it is not a training
     * the employee found and submitted on their own.
     *
     * @return BelongsTo<LdiTraining, $this>
     */
    public function ldiTraining(): BelongsTo
    {
        return $this->belongsTo(LdiTraining::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return HasMany<TrainingApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(TrainingApproval::class);
    }

    /**
     * What to print for the type of LD. An Other type carries its own text.
     *
     * @return Attribute<string, never>
     */
    protected function ldTypeLabel(): Attribute
    {
        return Attribute::get(fn (): string => $this->ld_type->requiresOwnText()
            ? (string) $this->ld_type_other
            : $this->ld_type->label());
    }

    /**
     * @param  Builder<TrainingRecord>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', TrainingStatus::Pending);
    }

    /**
     * @param  Builder<TrainingRecord>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', TrainingStatus::Approved);
    }

    /**
     * @param  Builder<TrainingRecord>  $query
     */
    public function scopeAwaiting(Builder $query, ApprovalLevel $level): void
    {
        $query->where('status', TrainingStatus::Pending)->where('current_level', $level);
    }

    /**
     * Pending records with nobody to approve them, because neither the
     * section nor the division has a head designated.
     *
     * @param  Builder<TrainingRecord>  $query
     */
    public function scopeUnroutable(Builder $query): void
    {
        $query->where('status', TrainingStatus::Pending)->whereNull('current_level');
    }
}
