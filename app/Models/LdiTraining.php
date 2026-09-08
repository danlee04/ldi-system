<?php

namespace App\Models;

use App\Concerns\HasInclusiveDates;
use App\Enums\LdType;
use Carbon\CarbonImmutable;
use Database\Factories\LdiTrainingFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A training the agency planned and funded, as opposed to one an employee
 * attended on their own and submitted for approval.
 *
 * @property int $id
 * @property string $title
 * @property string $development_partner who finances it
 * @property string $facilitator who conducts it — this is what a PDS prints
 * @property string|null $type_of_training
 * @property string|null $training_communication how the training came about
 * @property CarbonImmutable $date_start
 * @property CarbonImmutable $date_end
 * @property int $hours
 * @property float|null $cpd_units
 * @property LdType $ld_type
 * @property string|null $ld_type_other
 * @property string|null $location
 * @property int|null $target_attendees
 * @property string|null $budget
 * @property string|null $budget_source
 * @property int $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class LdiTraining extends Model
{
    /** @use HasFactory<LdiTrainingFactory> */
    use HasFactory, HasInclusiveDates;

    protected $fillable = [
        'title',
        'development_partner',
        'facilitator',
        'type_of_training',
        'training_communication',
        'date_start',
        'date_end',
        'hours',
        'cpd_units',
        'ld_type',
        'ld_type_other',
        'location',
        'target_attendees',
        'budget',
        'budget_source',
        'created_by',
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
            'cpd_units' => 'float',
            'ld_type' => LdType::class,
            'target_attendees' => 'integer',
            'budget' => 'decimal:2',
        ];
    }

    /**
     * One record per person who attended. This is the same table an
     * employee's own submissions live in, so training history, CPD units
     * and the PDS stay a single query.
     *
     * @return HasMany<TrainingRecord, $this>
     */
    public function trainingRecords(): HasMany
    {
        return $this->hasMany(TrainingRecord::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
     * What the attendees actually cost, against the planned budget.
     *
     * @return Attribute<float, never>
     */
    protected function actualSpend(): Attribute
    {
        return Attribute::get(fn (): float => (float) $this->trainingRecords()
            ->selectRaw('COALESCE(SUM(COALESCE(registration_fee, 0) + COALESCE(tev, 0) + COALESCE(expenses, 0)), 0) as total')
            ->value('total'));
    }
}
