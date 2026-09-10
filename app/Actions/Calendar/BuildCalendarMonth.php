<?php

namespace App\Actions\Calendar;

use Carbon\CarbonImmutable;

/**
 * A month drawn the way a wall calendar draws it: bars that run across the
 * days they last, stacked so none hides another.
 *
 * Both calendars in this app use it — the page and the dashboard's rail —
 * so a thing that runs Monday to Thursday is four days wide on each, and
 * the two never disagree about a month.
 *
 * @phpstan-type Entry array{key: string, kind: string, id: int, title: string, classes: string, start: CarbonImmutable, end: CarbonImmutable}
 * @phpstan-type Bar array{key: string, kind: string, id: int, title: string, classes: string, column: int, span: int, lane: int, opensBefore: bool, runsOn: bool}
 * @phpstan-type Week array{days: list<array{day: int|null, date: CarbonImmutable|null}>, bars: list<Bar>, lanes: int}
 */
class BuildCalendarMonth
{
    public function __construct(private readonly BuildMonthGrid $grid) {}

    /**
     * @param  list<Entry>  $entries
     * @return list<Week>
     */
    public function handle(CarbonImmutable $month, array $entries): array
    {
        $weeks = [];

        foreach ($this->grid->handle($month) as $week) {
            $dates = array_values(array_filter($week, fn (?CarbonImmutable $date): bool => $date !== null));

            $bars = $dates === []
                ? []
                : $this->barsAcross($entries, $dates[0], $dates[count($dates) - 1]);

            $weeks[] = [
                'days' => array_map(fn (?CarbonImmutable $date): array => [
                    'day' => $date?->day,
                    'date' => $date,
                ], $week),
                'bars' => $bars,
                'lanes' => $bars === [] ? 0 : max(array_column($bars, 'lane')) + 1,
            ];
        }

        return $weeks;
    }

    /**
     * Everything crossing one week, cut to that week and stacked.
     *
     * @param  list<Entry>  $entries
     * @return list<Bar>
     */
    private function barsAcross(array $entries, CarbonImmutable $weekStart, CarbonImmutable $weekEnd): array
    {
        $bars = [];

        foreach ($entries as $entry) {
            $bar = $this->barFor($entry, $weekStart, $weekEnd);

            if ($bar !== null) {
                $bars[] = $bar;
            }
        }

        // Longest first from each starting day, so a week-long bar takes the
        // top line and the short ones tuck under it.
        usort($bars, fn (array $a, array $b): int => [$a['column'], -$a['span']] <=> [$b['column'], -$b['span']]);

        return $this->stack($bars);
    }

    /**
     * One bar, cut to the week being drawn, or null when it does not reach
     * that week at all.
     *
     * @param  Entry  $entry
     * @return Bar|null
     */
    private function barFor(array $entry, CarbonImmutable $weekStart, CarbonImmutable $weekEnd): ?array
    {
        $start = $entry['start']->startOfDay();
        $end = $entry['end']->startOfDay();

        if ($start->gt($weekEnd) || $end->lt($weekStart)) {
            return null;
        }

        $from = $start->max($weekStart);
        $until = $end->min($weekEnd);

        return [
            'key' => $entry['key'],
            'kind' => $entry['kind'],
            'id' => $entry['id'],
            'title' => $entry['title'],
            'classes' => $entry['classes'],
            // CSS grid columns count from one, and the week starts on Sunday.
            'column' => $from->dayOfWeek + 1,
            'span' => (int) $from->diffInDays($until) + 1,
            'lane' => 0,
            'opensBefore' => $start->lt($weekStart),
            'runsOn' => $end->gt($weekEnd),
        ];
    }

    /**
     * Puts each bar on the first line where nothing is in its way.
     *
     * @param  list<Bar>  $bars
     * @return list<Bar>
     */
    private function stack(array $bars): array
    {
        /** @var array<int, array<int, bool>> $taken */
        $taken = [];

        foreach ($bars as $index => $bar) {
            $columns = range($bar['column'], $bar['column'] + $bar['span'] - 1);

            $lane = 0;

            while (! $this->fits($taken[$lane] ?? [], $columns)) {
                $lane++;
            }

            foreach ($columns as $column) {
                $taken[$lane][$column] = true;
            }

            $bars[$index]['lane'] = $lane;
        }

        return $bars;
    }

    /**
     * @param  array<int, bool>  $lane
     * @param  list<int>  $columns
     */
    private function fits(array $lane, array $columns): bool
    {
        foreach ($columns as $column) {
            if ($lane[$column] ?? false) {
                return false;
            }
        }

        return true;
    }
}
