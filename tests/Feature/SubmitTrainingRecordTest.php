<?php

use App\Actions\Training\SubmitTrainingRecord;
use App\Enums\ApprovalLevel;
use App\Enums\LdType;
use App\Enums\TrainingStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\TrainingRecord;
use App\Models\User;

function submissionAttributes(): array
{
    return [
        'title' => 'Records Management Seminar',
        'date_start' => '2026-03-02',
        'date_end' => '2026-03-04',
        'hours' => 24,
        'ld_type' => LdType::Technical,
        'conducted_by' => 'Civil Service Commission',
        'location' => 'Manila',
        'registration_fee' => 1500,
        'tev' => 2000,
    ];
}

test('a submitted record waits for the section head', function () {
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();
    $head = Employee::factory()->for($section)->create();
    $section->update(['section_head_employee_id' => $head->id]);

    $employee = Employee::factory()->for($section)->create();
    $user = User::factory()->employee()->create();

    $record = app(SubmitTrainingRecord::class)->handle($employee, submissionAttributes(), $user);

    expect($record->status)->toBe(TrainingStatus::Pending)
        ->and($record->current_level)->toBe(ApprovalLevel::SectionHead)
        ->and($record->submitted_by)->toBe($user->id)
        ->and($record->employee_id)->toBe($employee->id);
});

test('a record with no available head is unroutable but still saved', function () {
    $section = Section::factory()->create(['section_head_employee_id' => null]);
    $employee = Employee::factory()->for($section)->create();
    $user = User::factory()->employee()->create();

    $record = app(SubmitTrainingRecord::class)->handle($employee, submissionAttributes(), $user);

    expect($record->status)->toBe(TrainingStatus::Pending)
        ->and($record->current_level)->toBeNull()
        ->and(TrainingRecord::unroutable()->count())->toBe(1);
});

test('hr may submit on behalf of an employee', function () {
    $employee = Employee::factory()->create();
    $hr = User::factory()->hr()->create();

    $record = app(SubmitTrainingRecord::class)->handle($employee, submissionAttributes(), $hr);

    expect($record->employee_id)->toBe($employee->id)
        ->and($record->submitted_by)->toBe($hr->id);
});
