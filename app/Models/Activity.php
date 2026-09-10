<?php

namespace App\Models;

use App\Concerns\HasInclusiveDates;
use App\Enums\ActivityType;
use Carbon\CarbonImmutable;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anything the office puts on its calendar that is not a training: a
 * meeting, a holiday, a deadline.
 *
 * Trainings are planned as an LdiTraining and merely appear on the
 * calendar beside these. Nothing here is approved by anybody — the
 * calendar is a notice board, not a queue.
 *
 * @property int $id
 * @property string $title
 * @property ActivityType $type
 * @property CarbonImmutable $date_start
 * @property CarbonImmutable $date_end
 * @property string|null $time_start
 * @property string|null $time_end
 * @property string|null $location
 * @property string|null $description
 * @property int $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory, HasInclusiveDates;

    protected $fillable = [
        'title',
        'type',
        'date_start',
        'date_end',
        'time_start',
        'time_end',
        'location',
        'description',
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
            'type' => ActivityType::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Everything that touches the days between two dates.
     *
     * An activity that starts in March and ends in April belongs to both
     * months, so a month is asked for by overlap rather than by the day
     * the thing happens to start.
     *
     * @param  Builder<Activity>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonImmutable $from, CarbonImmutable $until): void
    {
        $query->whereDate('date_start', '<=', $until)->whereDate('date_end', '>=', $from);
    }

    /**
     * The hours it runs, or nothing when it takes the whole day.
     */
    public function timeRange(): ?string
    {
        if ($this->time_start === null) {
            return null;
        }

        $start = CarbonImmutable::parse($this->time_start)->format('g:i A');

        if ($this->time_end === null) {
            return $start;
        }

        return $start.' - '.CarbonImmutable::parse($this->time_end)->format('g:i A');
    }
}
