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
 * @phpstan-type Week array{days: list<array{day: int|null, date: CarbonImmutable|null}>, bars: list<Bar>, lanes: int, more: list<list<string>>}
 */
class BuildCalendarMonth
{
    public function __construct(private readonly BuildMonthGrid $grid) {}

    /**
     * @param  list<Entry>  $entries
     * @param  int|null  $maxLanes  The most bars a week may stack. What does not fit is left
     *                              off and named under each day it crosses, so a busy week
     *                              is no taller than a quiet one. Null stacks all of them.
     * @return list<Week>
     */
    public function handle(CarbonImmutable $month, array $entries, ?int $maxLanes = null): array
    {
        $weeks = [];

        foreach ($this->grid->handle($month) as $week) {
            $dates = array_values(array_filter($week, fn (?CarbonImmutable $date): bool => $date !== null));

            $bars = $dates === []
                ? []
                : $this->barsAcross($entries, $dates[0], $dates[count($dates) - 1]);

            $shown = array_values(array_filter($bars, fn (array $bar): bool => $maxLanes === null || $bar['lane'] < $maxLanes));

            $weeks[] = [
                'days' => array_map(fn (?CarbonImmutable $date): array => [
                    'day' => $date?->day,
                    'date' => $date,
                ], $week),
                'bars' => $shown,
                'lanes' => $shown === [] ? 0 : max(array_column($shown, 'lane')) + 1,
                'more' => $this->leftOff($bars, $maxLanes),
            ];
        }

        return $weeks;
    }

    /**
     * The titles of the bars that found no room, under each of the seven
     * days they cross.
     *
     * @param  list<Bar>  $bars
     * @return list<list<string>>
     */
    private function leftOff(array $bars, ?int $maxLanes): array
    {
        $more = array_fill(0, 7, []);

        foreach ($bars as $bar) {
            if ($maxLanes === null || $bar['lane'] < $maxLanes) {
                continue;
            }

            // Columns count from one; the days of a week from nought.
            foreach (range($bar['column'], $bar['column'] + $bar['span'] - 1) as $column) {
                $more[$column - 1][] = $bar['title'];
            }
        }

        return $more;
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
