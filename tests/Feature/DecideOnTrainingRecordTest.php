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

test('an hr officer who is also the designated head still decides', function () {
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();

    $hrUser = User::factory()->hr()->create();
    $head = Employee::factory()->for($section)->create(['user_id' => $hrUser->id]);
    $section->update(['section_head_employee_id' => $head->id]);

    $employee = Employee::factory()->for($section)->create();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);

    expect($hrUser->can('decide', $record))->toBeTrue();
});

test('the head at the current level may decide, and so may hr', function () {
    ['employee' => $employee, 'sectionHead' => $sectionHead, 'divisionHead' => $divisionHead] = staffedSection();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);

    expect($sectionHead->user->can('decide', $record))->toBeTrue()
        ->and($divisionHead->user->can('decide', $record))->toBeFalse()
        ->and(User::factory()->hr()->create()->can('decide', $record))->toBeTrue()
        ->and(User::factory()->employee()->create()->can('decide', $record))->toBeFalse();
});

test('hr clears a record that has no approver at all', function () {
    $section = Section::factory()->create(['section_head_employee_id' => null]);
    $employee = Employee::factory()->for($section)->create();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => null]);

    $hr = User::factory()->hr()->create();

    expect($hr->can('decide', $record))->toBeTrue();

    $decided = app(DecideOnTrainingRecord::class)->handle($record, $hr, ApprovalDecision::Approved);

    expect($decided->status)->toBe(TrainingStatus::Approved)
        ->and($decided->current_level)->toBeNull()
        // Recorded against HR, because no head ever saw it.
        ->and($decided->approvals->first()->level)->toBe(ApprovalLevel::Hr)
        ->and($decided->approvals->first()->approver_user_id)->toBe($hr->id);
});

test('nobody but hr and admin can decide a record with no approver', function () {
    $section = Section::factory()->create(['section_head_employee_id' => null]);
    $employee = Employee::factory()->for($section)->create(['user_id' => User::factory()->employee()]);
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => null]);

    expect($employee->user->can('decide', $record))->toBeFalse();

    app(DecideOnTrainingRecord::class)->handle($record, $employee->user, ApprovalDecision::Approved);
})->throws(InvalidArgumentException::class, 'This record has no approver assigned.');

test('hr deciding at a head level lets the record carry on up the chain', function () {
    ['employee' => $employee] = staffedSection();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);

    $decided = app(DecideOnTrainingRecord::class)->handle(
        $record,
        User::factory()->hr()->create(),
        ApprovalDecision::Approved,
    );

    // HR stood in for the section head; the division head still reviews it.
    expect($decided->status)->toBe(TrainingStatus::Pending)
        ->and($decided->current_level)->toBe(ApprovalLevel::DivisionHead)
        ->and($decided->approvals->first()->level)->toBe(ApprovalLevel::SectionHead);
});

test('an approved record cannot be decided again, even by hr', function () {
    $employee = Employee::factory()->create();
    $record = TrainingRecord::factory()->for($employee)->approved()->create();

    expect(User::factory()->hr()->create()->can('decide', $record))->toBeFalse();
});
