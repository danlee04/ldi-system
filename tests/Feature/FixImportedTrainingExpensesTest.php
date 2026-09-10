<?php

use App\Models\TrainingRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config()->set('ldi.legacy_connection', config('database.default'));
    config()->set('ldi.legacy_tables', ['trainings' => 'legacy_trainings']);

    Schema::create('legacy_trainings', function ($table) {
        $table->id('training_id');
        $table->string('training_title');
        $table->date('date_start');
        $table->decimal('expenses', 10, 2)->nullable();
        $table->decimal('registration_fee', 10, 2)->nullable();
        $table->decimal('tev', 10, 2)->nullable();
    });
});

/**
 * The legacy row and the record the import made from it.
 */
function doubledPair(array $amounts = []): TrainingRecord
{
    $amounts = [...['registration_fee' => 1500, 'tev' => 2000, 'expenses' => 3500], ...$amounts];

    DB::table('legacy_trainings')->insert([
        'training_id' => 1,
        'training_title' => 'Records Management Seminar',
        'date_start' => '2025-03-02',
        ...$amounts,
    ]);

    return TrainingRecord::factory()->create([
        'title' => 'Records Management Seminar',
        'date_start' => '2025-03-02',
        ...$amounts,
    ]);
}

test('it reports the double counting without writing anything', function () {
    $record = doubledPair();

    $this->artisan('ldi:fix-imported-expenses')
        ->expectsOutputToContain('1 imported record(s) count the same money twice.')
        ->assertSuccessful();

    expect((float) $record->fresh()->expenses)->toBe(3500.0);
});

test('it clears the duplicated expense when told to apply', function () {
    $record = doubledPair();

    $this->artisan('ldi:fix-imported-expenses', ['--apply' => true])->assertSuccessful();

    $record = $record->fresh();

    expect($record->expenses)->toBeNull()
        // What is left is the cost as the legacy row actually stated it.
        ->and((float) $record->registration_fee + (float) $record->tev)->toBe(3500.0);
});

test('it leaves a figure typed in this app alone', function () {
    // The same shape of amounts, but no legacy row it could have come from.
    $typed = TrainingRecord::factory()->create([
        'title' => 'Gender Sensitivity Orientation',
        'date_start' => '2025-06-10',
        'registration_fee' => 500,
        'tev' => 250,
        'expenses' => 750,
    ]);

    doubledPair();

    $this->artisan('ldi:fix-imported-expenses', ['--apply' => true])
        ->expectsOutputToContain('typed in this app')
        ->assertSuccessful();

    expect((float) $typed->fresh()->expenses)->toBe(750.0);
});

test('running it a second time finds nothing left to correct', function () {
    doubledPair();

    $this->artisan('ldi:fix-imported-expenses', ['--apply' => true])->assertSuccessful();

    $this->artisan('ldi:fix-imported-expenses', ['--apply' => true])
        ->expectsOutputToContain('No imported record still carries a duplicated cost.')
        ->assertSuccessful();
});

test('a record with a real third expense is not touched', function () {
    $record = doubledPair(['registration_fee' => 1500, 'tev' => 2000, 'expenses' => 700]);

    $this->artisan('ldi:fix-imported-expenses', ['--apply' => true])->assertSuccessful();

    expect((float) $record->fresh()->expenses)->toBe(700.0);
});
