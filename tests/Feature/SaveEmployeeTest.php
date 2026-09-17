<?php

use App\Actions\Employees\SaveEmployee;
use App\Enums\EmploymentStatus;
use App\Models\Division;
use App\Models\Eligibility;
use App\Models\Employee;
use App\Models\Section;

/**
 * The fields the form would have validated and handed to the action.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function employeeAttributes(array $overrides = []): array
{
    return [
        'first_name' => 'Andres',
        'middle_name' => '',
        'last_name' => 'Bonifacio',
        'suffix' => '',
        'gender' => '',
        'positionId' => null,
        'item_number' => '',
        'employeeDivisionId' => null,
        'employeeSectionId' => null,
        'employment_status' => EmploymentStatus::Permanent->value,
        'eligibilityId' => null,
        'eligibilityExpiresOn' => '',
        ...$overrides,
    ];
}

test('a box left blank is stored as nothing rather than as an empty string', function () {
    $employee = app(SaveEmployee::class)->handle(null, employeeAttributes());

    expect($employee->middle_name)->toBeNull()
        ->and($employee->suffix)->toBeNull()
        ->and($employee->gender)->toBeNull()
        ->and($employee->item_number)->toBeNull();
});

test('editing keeps the same roster line rather than adding another', function () {
    $existing = Employee::factory()->create();

    $saved = app(SaveEmployee::class)->handle($existing, employeeAttributes(['last_name' => 'Renamed']));

    expect($saved->getKey())->toBe($existing->getKey())
        ->and(Employee::count())->toBe(1)
        ->and($existing->fresh()->last_name)->toBe('Renamed');
});

test('the plantilla item is kept against the person, not the position', function () {
    $employee = app(SaveEmployee::class)->handle(null, employeeAttributes(['item_number' => 'ADOF3-14-2019']));

    expect($employee->item_number)->toBe('ADOF3-14-2019');
});

test('the division is taken from the section rather than from the form', function () {
    $wanted = Division::factory()->create();
    $section = Section::factory()->for($wanted)->create();
    $other = Division::factory()->create();

    // The form's division only narrows the section picker, so even a
    // mismatched one must not land on the record.
    $employee = app(SaveEmployee::class)->handle(null, employeeAttributes([
        'employeeSectionId' => $section->id,
        'employeeDivisionId' => $other->id,
    ]));

    expect($employee->fresh()->division_id)->toBe($wanted->id);
});

test('an eligibility and its expiry are written onto the employee own sheet', function () {
    $eligibility = Eligibility::factory()->create(['name' => 'Career Service Professional']);

    $employee = app(SaveEmployee::class)->handle(null, employeeAttributes([
        'eligibilityId' => $eligibility->id,
        'eligibilityExpiresOn' => '2030-04-30',
    ]));

    $line = $employee->eligibilities()->first();

    expect($line->eligibility_id)->toBe($eligibility->id)
        ->and($line->date_of_validity->toDateString())->toBe('2030-04-30')
        ->and($employee->eligibilities()->count())->toBe(1);
});

test('saving again edits the same eligibility line instead of adding a second', function () {
    $first = Eligibility::factory()->create();
    $second = Eligibility::factory()->create();

    $employee = app(SaveEmployee::class)->handle(null, employeeAttributes(['eligibilityId' => $first->id]));

    app(SaveEmployee::class)->handle($employee, employeeAttributes(['eligibilityId' => $second->id]));

    expect($employee->eligibilities()->count())->toBe(1)
        ->and($employee->eligibilities()->first()->eligibility_id)->toBe($second->id);
});

test('leaving the eligibility empty leaves what is already on the sheet alone', function () {
    $eligibility = Eligibility::factory()->create();

    $employee = app(SaveEmployee::class)->handle(null, employeeAttributes(['eligibilityId' => $eligibility->id]));

    $employee->eligibilities()->first()->update(['rating' => '87.5', 'place_of_examination' => 'Butuan City']);

    app(SaveEmployee::class)->handle($employee, employeeAttributes(['eligibilityId' => null]));

    $line = $employee->eligibilities()->first();

    // The rating and the place are only on My PDS, so a blank here must
    // not take them with it.
    expect($line)->not->toBeNull()
        ->and($line->rating)->toBe('87.5')
        ->and($line->place_of_examination)->toBe('Butuan City');
});
