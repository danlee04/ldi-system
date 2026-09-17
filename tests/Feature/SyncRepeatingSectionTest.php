<?php

use App\Actions\Pds\SyncRepeatingSection;
use App\Models\Employee;
use App\Models\EmployeeWorkExperience;
use Illuminate\Database\QueryException;

/**
 * One line of Section V, as the form hands it over.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function workLine(array $overrides = []): array
{
    return [
        'id' => null,
        'from_date' => '2020-01-06',
        'to_date' => '',
        'position_title' => 'Administrative Assistant II',
        'agency_name' => 'DOH Treatment and Rehabilitation Center Caraga',
        'monthly_salary' => '',
        'salary_grade' => '',
        'appointment_status' => 'Permanent',
        'is_government' => true,
        ...$overrides,
    ];
}

test('the id behind each line comes back, so the next save edits it rather than adding another', function () {
    $employee = Employee::factory()->create();

    $ids = app(SyncRepeatingSection::class)->handle(
        $employee,
        'workExperiences',
        collect([workLine(), workLine(['position_title' => 'Administrative Officer III'])]),
        [null, null],
    );

    expect($ids)->toHaveCount(2);

    app(SyncRepeatingSection::class)->handle(
        $employee,
        'workExperiences',
        collect([workLine(['position_title' => 'Renamed'])]),
        [$ids[0]],
    );

    $postings = EmployeeWorkExperience::where('employee_id', $employee->getKey())->get();

    expect($postings)->toHaveCount(1)
        ->and($postings->first()->getKey())->toBe($ids[0])
        ->and($postings->first()->position_title)->toBe('Renamed');
});

test('a line that fails to save takes the rest of the section down with it', function () {
    $employee = Employee::factory()->create();

    $kept = EmployeeWorkExperience::factory()->for($employee)->create();

    // The second line has no date, which the column will not take. The
    // first must not survive it, or the employee is left looking at half
    // a saved section.
    expect(fn () => app(SyncRepeatingSection::class)->handle(
        $employee,
        'workExperiences',
        collect([workLine(), workLine(['from_date' => ''])]),
        [null, null],
    ))->toThrow(QueryException::class);

    $postings = EmployeeWorkExperience::where('employee_id', $employee->getKey())->get();

    expect($postings)->toHaveCount(1)
        ->and($postings->first()->getKey())->toBe($kept->getKey());
});
