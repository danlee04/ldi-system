<?php

namespace App\Actions\Reports;

use Carbon\CarbonImmutable;

/**
 * A quarter of a year, as a pair of dates.
 *
 * MySQL has QUARTER() and SQLite does not, so the quarter is turned into
 * a date range here rather than asked of the database. The reports then
 * read the same on the server and in the test suite.
 */
class Quarter
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function bounds(int $year, int $quarter): array
    {
        $start = CarbonImmutable::create($year, ($quarter - 1) * 3 + 1, 1);

        return [$start->startOfDay(), $start->addMonths(3)->subDay()->endOfDay()];
    }

    /**
     * Which quarter a date falls in.
     */
    public static function of(CarbonImmutable $date): int
    {
        return (int) ceil($date->month / 3);
    }

    /**
     * @return list<int>
     */
    public static function all(): array
    {
        return [1, 2, 3, 4];
    }
}
