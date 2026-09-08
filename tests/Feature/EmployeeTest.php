<?php

use App\Models\Employee;
use App\Models\Section;
use Illuminate\Database\QueryException;

test('an employee belongs to a section and a division', function () {
    $section = Section::factory()->create();
    $employee = Employee::factory()->for($section)->create();

    expect($employee->section->id)->toBe($section->id)
        ->and($employee->division->id)->toBe($section->division_id);
});

test('saving an employee keeps division in step with section', function () {
    $section = Section::factory()->create();
    $employee = Employee::factory()->create(['section_id' => $section->id, 'division_id' => null]);

    expect($employee->fresh()->division_id)->toBe($section->division_id);
});

test('full name joins the parts and omits blanks', function () {
    $employee = Employee::factory()->create([
        'first_name' => 'Maria',
        'middle_name' => 'Santos',
        'last_name' => 'Cruz',
        'suffix' => null,
    ]);

    expect($employee->full_name)->toBe('Maria Santos Cruz');
});

test('full name includes the suffix when present', function () {
    $employee = Employee::factory()->create([
        'first_name' => 'Jose',
        'middle_name' => null,
        'last_name' => 'Rizal',
        'suffix' => 'Jr.',
    ]);

    expect($employee->full_name)->toBe('Jose Rizal Jr.');
});

test('the active scope excludes inactive employees', function () {
    Employee::factory()->count(2)->create();
    Employee::factory()->inactive()->create();

    expect(Employee::count())->toBe(3)
        ->and(Employee::active()->count())->toBe(2);
});

test('employee numbers are unique', function () {
    Employee::factory()->create(['employee_number' => 'EMP-001']);

    expect(fn () => Employee::factory()->create(['employee_number' => 'EMP-001']))
        ->toThrow(QueryException::class);
});
