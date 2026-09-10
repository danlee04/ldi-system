<?php

namespace App\Actions\Calendar;

use Carbon\CarbonImmutable;

/**
 * A month laid out as the weeks a calendar draws.
 *
 * One definition, because the dashboard's month and the calendar page's
 * month must line up — a day that sits under Wednesday on one and under
 * Friday on the other is worse than no calendar at all.
 */
class BuildMonthGrid
{
    /**
     * Weeks of seven, padded with null where the month has not started or
     * has already ended.
     *
     * @return list<list<CarbonImmutable|null>>
     */
    public function handle(CarbonImmutable $month): array
    {
        $month = $month->startOfMonth();
        $days = [];

        // Blank cells so the first of the month lands under its weekday.
        // A month starting on Sunday needs none, which is why this counts
        // rather than ranging — range(1, 0) walks backwards and would push
        // the whole month two days along.
        for ($blank = 0; $blank < $month->dayOfWeek; $blank++) {
            $days[] = null;
        }

        foreach (range(1, $month->daysInMonth) as $day) {
            $days[] = $month->addDays($day - 1);
        }

        while (count($days) % 7 !== 0) {
            $days[] = null;
        }

        return array_chunk($days, 7);
    }
}
