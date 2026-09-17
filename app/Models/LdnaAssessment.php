<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LdnaAssessmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One person's assessment in one cycle.
 *
 * @property int $id
 * @property int $ldna_cycle_id
 * @property int $employee_id
 * @property int|null $position_id
 * @property CarbonImmutable|null $self_submitted_at
 * @property CarbonImmutable|null $confirmed_at
 * @property int|null $confirmed_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class LdnaAssessment extends Model
{
    /** @use HasFactory<LdnaAssessmentFactory> */
    use HasFactory;

    protected $fillable = ['ldna_cycle_id', 'employee_id', 'position_id', 'self_submitted_at', 'confirmed_at', 'confirmed_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'self_submitted_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LdnaCycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(LdnaCycle::class, 'ldna_cycle_id');
    }

    /**
     * Somebody who has since left keeps their place in the years they were
     * assessed in.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * @return HasMany<LdnaRating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(LdnaRating::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isSelfSubmitted(): bool
    {
        return $this->self_submitted_at !== null;
    }

    /**
     * Whether the head has read what they said about themselves and
     * agreed to it. Only a confirmed assessment counts toward the gap.
     */
    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }
}
