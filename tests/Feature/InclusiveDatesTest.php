<?php

use App\Models\LdiTraining;
use App\Models\TrainingRecord;

test('a range inside one month states the month and year once', function () {
    $record = TrainingRecord::factory()->create([
        'date_start' => '2026-02-20',
        'date_end' => '2026-02-23',
    ]);

    expect($record->inclusive_dates)->toBe('February 20-23, 2026');
});

test('a single day is written in full', function () {
    $record = TrainingRecord::factory()->create([
        'date_start' => '2026-02-20',
        'date_end' => '2026-02-20',
    ]);

    expect($record->inclusive_dates)->toBe('February 20, 2026');
});

test('a range crossing months names both months and the year once', function () {
    $record = TrainingRecord::factory()->create([
        'date_start' => '2026-02-26',
        'date_end' => '2026-03-02',
    ]);

    expect($record->inclusive_dates)->toBe('February 26 - March 2, 2026');
});

test('a range crossing years states both years', function () {
    $record = TrainingRecord::factory()->create([
        'date_start' => '2025-12-28',
        'date_end' => '2026-01-03',
    ]);

    expect($record->inclusive_dates)->toBe('December 28, 2025 - January 3, 2026');
});

test('a planned training is written the same way', function () {
    $plan = LdiTraining::factory()->create([
        'date_start' => '2026-05-04',
        'date_end' => '2026-05-08',
    ]);

    expect($plan->inclusive_dates)->toBe('May 4-8, 2026');
});
