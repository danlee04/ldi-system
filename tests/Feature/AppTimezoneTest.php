<?php

use Illuminate\Support\Carbon;

test('the new year starts at midnight in Manila, not eight hours later', function () {
    // 00:30 on New Year's Day in Manila is still the old year in UTC.
    $this->travelTo(Carbon::parse('2026-12-31 16:30:00', 'UTC'));

    expect(today()->toDateString())->toBe('2027-01-01')
        ->and(now()->year)->toBe(2027);
});
