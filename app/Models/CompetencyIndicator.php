<?php

namespace App\Models;

use App\Enums\ProficiencyLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one competency looks like at one level — the words a rater reads
 * before picking the level that fits.
 *
 * @property int $id
 * @property int $competency_id
 * @property ProficiencyLevel $level
 * @property string $description
 */
class CompetencyIndicator extends Model
{
    protected $fillable = ['competency_id', 'level', 'description'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['level' => ProficiencyLevel::class];
    }

    /**
     * @return BelongsTo<Competency, $this>
     */
    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }
}
