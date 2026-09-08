<?php

namespace App\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Inclusive dates written the way a CS Form 212 writes them.
 *
 * The month and the year are stated once when both ends share them:
 * "February 20-23, 2026", not "20 Feb 2026 – 23 Feb 2026".
 *
 * @property CarbonImmutable $date_start
 * @property CarbonImmutable $date_end
 */
trait HasInclusiveDates
{
    /**
     * Always produces text — a record without both dates cannot be saved.
     *
     * @return Attribute<non-falsy-string, never>
     */
    protected function inclusiveDates(): Attribute
    {
        return Attribute::get(function (): string {
            $start = $this->date_start;
            $end = $this->date_end;

            if ($start->isSameDay($end)) {
                return $start->format('F j, Y');
            }

            if ($start->year !== $end->year) {
                return $start->format('F j, Y').' - '.$end->format('F j, Y');
            }

            if ($start->month !== $end->month) {
                return $start->format('F j').' - '.$end->format('F j, Y');
            }

            return $start->format('F j').'-'.$end->format('j, Y');
        });
    }
}
