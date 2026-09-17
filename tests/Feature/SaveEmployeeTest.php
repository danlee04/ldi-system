<?php

use App\Actions\Employees\SaveEmployee;
use App\Enums\EmploymentStatus;
use App\Models\Employee;

/**
 * The fields the form would have validated and handed to the action.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function employeeAttributes(array $overrides = []): array
{
    return [
        'employee_number' => 'EMP-401',
        'first_name' => 'Andres',
        'middle_name' => '',
        'last_name' => 'Bonifacio',
        'suffix' => '',
        'gender' => '',
        'positionId' => null,
        'employeeSectionId' => null,
        'employment_status' => EmploymentStatus::Permanent->value,
        'date_hired' => '',
        'is_active' => true,
        ...$overrides,
    ];
}

test('a box left blank is stored as nothing rather than as an empty string', function () {
    $employee = app(SaveEmployee::class)->handle(null, employeeAttributes());

    expect($employee->middle_name)->toBeNull()
        ->and($employee->suffix)->toBeNull()
        ->and($employee->gender)->toBeNull()
        ->and($employee->date_hired)->toBeNull();
});

test('editing keeps the same roster line rather than adding another', function () {
    $existing = Employee::factory()->create();

    $saved = app(SaveEmployee::class)->handle($existing, employeeAttributes([
        'employee_number' => $existing->employee_number,
        'last_name' => 'Renamed',
    ]));

    expect($saved->getKey())->toBe($existing->getKey())
        ->and(Employee::count())->toBe(1)
        ->and($existing->fresh()->last_name)->toBe('Renamed');
});
