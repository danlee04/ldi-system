<?php

namespace App\Models;

use App\Enums\ProficiencyLevel;
use Database\Factories\LdnaRatingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One competency on one person's assessment.
 *
 * @property int $id
 * @property int $ldna_assessment_id
 * @property int $competency_id
 * @property ProficiencyLevel $required_level copied in when the assessment was made
 * @property ProficiencyLevel|null $self_level
 * @property string|null $remarks what the head noted when they confirmed it
 */
class LdnaRating extends Model
{
    /** @use HasFactory<LdnaRatingFactory> */
    use HasFactory;

    protected $fillable = ['ldna_assessment_id', 'competency_id', 'required_level', 'self_level', 'remarks'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'required_level' => ProficiencyLevel::class,
            'self_level' => ProficiencyLevel::class,
        ];
    }

    /**
     * @return BelongsTo<LdnaAssessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(LdnaAssessment::class, 'ldna_assessment_id');
    }

    /**
     * @return BelongsTo<Competency, $this>
     */
    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    /**
     * How many levels short of the requirement they put themselves.
     *
     * Null until they have answered. Never below zero — being above the
     * requirement is not a need.
     */
    public function gap(): ?int
    {
        if ($this->self_level === null) {
            return null;
        }

        return max(0, $this->required_level->rank() - $this->self_level->rank());
    }
}
