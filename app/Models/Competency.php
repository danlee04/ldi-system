<?php

namespace App\Models;

use App\Enums\CompetencyType;
use App\Enums\ProficiencyLevel;
use Carbon\CarbonImmutable;
use Database\Factories\CompetencyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One entry of the competency dictionary: something the Center expects
 * people to be able to do, described at each of the four levels.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property CompetencyType $type
 * @property ProficiencyLevel|null $required_level Core and Leadership only
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Competency extends Model
{
    /** @use HasFactory<CompetencyFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description', 'type', 'required_level', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CompetencyType::class,
            'required_level' => ProficiencyLevel::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * What the competency looks like at each level. Four of them.
     *
     * @return HasMany<CompetencyIndicator, $this>
     */
    public function indicators(): HasMany
    {
        return $this->hasMany(CompetencyIndicator::class);
    }

    /**
     * The positions that need this competency, each with the level it
     * needs. Technical competencies only.
     *
     * @return BelongsToMany<Position, $this>
     */
    public function positions(): BelongsToMany
    {
        return $this->belongsToMany(Position::class)->withPivot('required_level')->withTimestamps();
    }

    /**
     * @return HasMany<LdnaRating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(LdnaRating::class);
    }

    /**
     * Whether somebody has been assessed on it. Such a competency is
     * deactivated rather than deleted, or a past year loses its answers.
     */
    public function isInUse(): bool
    {
        return $this->ratings()->exists();
    }

    /**
     * The LDI plans that set out to build it.
     *
     * @return BelongsToMany<LdiTraining, $this>
     */
    public function ldiTrainings(): BelongsToMany
    {
        return $this->belongsToMany(LdiTraining::class)->withTimestamps();
    }

    /**
     * What the competency reads as at the given level.
     */
    public function indicatorFor(ProficiencyLevel $level): ?string
    {
        return $this->indicators->firstWhere('level', $level)?->description;
    }

    /**
     * @param  Builder<Competency>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
