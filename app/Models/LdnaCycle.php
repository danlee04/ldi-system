<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LdnaCycleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * One year's needs assessment. The year is the one being planned for, so
 * LDNA 2027 is carried out towards the end of 2026.
 *
 * @property int $id
 * @property int $year
 * @property CarbonImmutable $opens_on
 * @property CarbonImmutable $closes_on
 * @property int $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class LdnaCycle extends Model
{
    /** @use HasFactory<LdnaCycleFactory> */
    use HasFactory;

    protected $fillable = ['year', 'opens_on', 'closes_on', 'created_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'opens_on' => 'date',
            'closes_on' => 'date',
        ];
    }

    /**
     * @return HasMany<LdnaAssessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(LdnaAssessment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whether anybody can rate today. Both ends of the window count.
     */
    public function isOpen(): bool
    {
        return today()->betweenIncluded($this->opens_on, $this->closes_on);
    }

    public function hasClosed(): bool
    {
        return today()->greaterThan($this->closes_on);
    }

    public function status(): string
    {
        return match (true) {
            $this->hasClosed() => __('Closed'),
            $this->isOpen() => __('Open'),
            default => __('Upcoming'),
        };
    }

    /**
     * Refuses a rating outside the window. Checked by the actions, not only
     * hidden by the screens.
     *
     * @throws ValidationException
     */
    public function ensureOpen(): void
    {
        if (! $this->isOpen()) {
            throw ValidationException::withMessages([
                'cycle' => __('LDNA :year is not open for rating.', ['year' => $this->year]),
            ]);
        }
    }

    /**
     * Refuses a change to a cycle that is over. HR may still prepare one
     * that has yet to open.
     *
     * @throws ValidationException
     */
    public function ensureNotClosed(): void
    {
        if ($this->hasClosed()) {
            throw ValidationException::withMessages([
                'cycle' => __('LDNA :year has closed.', ['year' => $this->year]),
            ]);
        }
    }

    /**
     * The cycle whose window takes in today, if one does.
     */
    public static function current(): ?self
    {
        return self::query()
            ->whereDate('opens_on', '<=', today())
            ->whereDate('closes_on', '>=', today())
            ->orderByDesc('year')
            ->first();
    }
}
