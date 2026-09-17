<?php

use App\Models\Employee;
use App\Models\Position;
use Illuminate\Support\Facades\DB;

/**
 * Runs the one migration under test against the schema as it was before
 * the column went, so the move can be proved on real-shaped data.
 */
function runTheItemMove(): void
{
    require_once database_path('migrations/2026_09_17_060837_move_position_item_numbers_to_employees.php');

    (include database_path('migrations/2026_09_17_060837_move_position_item_numbers_to_employees.php'))->up();
}

beforeEach(function (): void {
    // The column is dropped by the migration after the one being tested,
    // so it is put back for the length of this file.
    DB::statement('ALTER TABLE positions ADD COLUMN item_number VARCHAR(255) NULL');
});

test('a position held by one person hands its item number to them', function () {
    $position = Position::factory()->create(['title' => 'STATISTICIAN II']);
    $employee = Employee::factory()->for($position)->create(['item_number' => null]);

    DB::table('positions')->where('id', $position->id)->update(['item_number' => 'OSEC-DOHB-STAT2-97-2014']);

    runTheItemMove();

    expect($employee->fresh()->item_number)->toBe('OSEC-DOHB-STAT2-97-2014');
});

test('a position held by several people hands its item number to nobody', function () {
    $position = Position::factory()->create(['title' => 'NURSE I']);
    $five = Employee::factory()->count(5)->for($position)->create(['item_number' => null]);

    DB::table('positions')->where('id', $position->id)->update(['item_number' => 'OSEC-DOHB-NUR1-314-2014']);

    runTheItemMove();

    // Only HR knows which of the five sits in 314-2014, so a guess is
    // worse than leaving it to them.
    expect($five->every(fn (Employee $employee): bool => $employee->fresh()->item_number === null))->toBeTrue();
});

test('somebody who already has an item keeps the one they have', function () {
    $position = Position::factory()->create();
    $employee = Employee::factory()->for($position)->create(['item_number' => 'ALREADY-MINE']);

    DB::table('positions')->where('id', $position->id)->update(['item_number' => 'FROM-THE-POSITION']);

    runTheItemMove();

    expect($employee->fresh()->item_number)->toBe('ALREADY-MINE');
});

test('an item somebody else already sits in is not handed out twice', function () {
    $position = Position::factory()->create();
    $holder = Employee::factory()->for($position)->create(['item_number' => null]);

    Employee::factory()->create(['item_number' => 'OSEC-DOHB-STAT2-97-2014']);

    DB::table('positions')->where('id', $position->id)->update(['item_number' => 'OSEC-DOHB-STAT2-97-2014']);

    runTheItemMove();

    expect($holder->fresh()->item_number)->toBeNull();
});

test('somebody who has left the roster does not count as the holder', function () {
    $position = Position::factory()->create();
    $gone = Employee::factory()->for($position)->create(['item_number' => null]);
    $here = Employee::factory()->for($position)->create(['item_number' => null]);

    $gone->delete();

    DB::table('positions')->where('id', $position->id)->update(['item_number' => 'OSEC-DOHB-STAT2-97-2014']);

    runTheItemMove();

    // One person is left on the roster, so the item is theirs.
    expect($here->fresh()->item_number)->toBe('OSEC-DOHB-STAT2-97-2014');
});
