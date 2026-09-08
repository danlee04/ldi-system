<?php

use App\Actions\Training\DecideOnTrainingRecord;
use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use App\Enums\TrainingStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\TrainingRecord;
use App\Models\User;

/**
 * A section with both heads designated, and one ordinary employee in it.
 *
 * @return array{employee: Employee, sectionHead: Employee, divisionHead: Employee}
 */
function staffedSection(): array
{
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();

    $sectionHead = Employee::factory()->for($section)->create(['user_id' => User::factory()->sectionHead()]);
    $divisionHead = Employee::factory()->for($section)->create(['user_id' => User::factory()->divisionHead()]);

    $section->update(['section_head_employee_id' => $sectionHead->id]);
    $division->update(['division_head_employee_id' => $divisionHead->id]);

    return [
        'employee' => Employee::factory()->for($section)->create(),
        'sectionHead' => $sectionHead,
        'divisionHead' => $divisionHead,
    ];
}

test('section head approval advances the record to the division head', function () {
    ['employee' => $employee, 'sectionHead' => $sectionHead] = staffedSection();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);

    $record = app(DecideOnTrainingRecord::class)
        ->handle($record, $sectionHead->user, ApprovalDecision::Approved, 'Endorsed.');

    expect($record->status)->toBe(TrainingStatus::Pending)
        ->and($record->current_level)->toBe(ApprovalLevel::DivisionHead)
        ->and($record->approvals)->toHaveCount(1);
});

test('division head approval completes the record', function () {
    ['employee' => $employee, 'divisionHead' => $divisionHead] = staffedSection();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::DivisionHead]);

    $record = app(DecideOnTrainingRecord::class)
        ->handle($record, $divisionHead->user, ApprovalDecision::Approved);

    expect($record->status)->toBe(TrainingStatus::Approved)
        ->and($record->current_level)->toBeNull();
});

test('both approvals are kept in the trail', function () {
    ['employee' => $employee, 'sectionHead' => $sectionHead, 'divisionHead' => $divisionHead] = staffedSection();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);
    $action = app(DecideOnTrainingRecord::class);

    $record = $action->handle($record, $sectionHead->user, ApprovalDecision::Approved);
    $record = $action->handle($record, $divisionHead->user, ApprovalDecision::Approved);

    expect($record->approvals)->toHaveCount(2)
        ->and($record->approvals->pluck('level')->all())
        ->toBe([ApprovalLevel::SectionHead, ApprovalLevel::DivisionHead]);
});

test('a rejection ends the record and keeps the reason', function () {
    ['employee' => $employee, 'sectionHead' => $sectionHead] = staffedSection();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);

    $record = app(DecideOnTrainingRecord::class)
        ->handle($record, $sectionHead->user, ApprovalDecision::Rejected, 'Outside the training plan.');

    expect($record->status)->toBe(TrainingStatus::Rejected)
        ->and($record->current_level)->toBeNull()
        ->and($record->rejection_reason)->toBe('Outside the training plan.');
});

test('an already decided record cannot be decided again', function () {
    ['employee' => $employee, 'sectionHead' => $sectionHead] = staffedSection();
    $record = TrainingRecord::factory()->for($employee)->approved()->create();

    expect(fn () => app(DecideOnTrainingRecord::class)
        ->handle($record, $sectionHead->user, ApprovalDecision::Approved))
        ->toThrow(InvalidArgumentException::class);
});

test('an unroutable record cannot be decided', function () {
    ['employee' => $employee, 'sectionHead' => $sectionHead] = staffedSection();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => null]);

    expect(fn () => app(DecideOnTrainingRecord::class)
        ->handle($record, $sectionHead->user, ApprovalDecision::Approved))
        ->toThrow(InvalidArgumentException::class);
});

test('only the designated head at the current level may decide', function () {
    ['employee' => $employee, 'sectionHead' => $sectionHead, 'divisionHead' => $divisionHead] = staffedSection();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);

    expect($sectionHead->user->can('decide', $record))->toBeTrue()
        ->and($divisionHead->user->can('decide', $record))->toBeFalse()
        ->and(User::factory()->hr()->create()->can('decide', $record))->toBeFalse();
});
