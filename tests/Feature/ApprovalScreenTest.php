<?php

use App\Enums\ApprovalLevel;
use App\Enums\TrainingStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\TrainingRecord;
use App\Models\User;
use Livewire\Livewire;

/**
 * A fully staffed section: both a section head and a division head.
 *
 * The division head matters even though no test acts as one — without it
 * a section head approval would complete the record instead of advancing
 * it, and the two-step chain would never be exercised here.
 *
 * @return array{employee: Employee, sectionHeadUser: User}
 */
function sectionWithHead(): array
{
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();

    $headUser = User::factory()->sectionHead()->create();
    $head = Employee::factory()->for($section)->create(['user_id' => $headUser->id]);
    $section->update(['section_head_employee_id' => $head->id]);

    $divisionHead = Employee::factory()->for($section)->create([
        'user_id' => User::factory()->divisionHead(),
    ]);
    $division->update(['division_head_employee_id' => $divisionHead->id]);

    return [
        'employee' => Employee::factory()->for($section)->create(),
        'sectionHeadUser' => $headUser,
    ];
}

test('the queue shows only records waiting on me', function () {
    ['employee' => $employee, 'sectionHeadUser' => $headUser] = sectionWithHead();

    TrainingRecord::factory()->for($employee)->create([
        'title' => 'Mine To Decide',
        'current_level' => ApprovalLevel::SectionHead,
    ]);
    TrainingRecord::factory()->create(['title' => 'Someone Elses Queue']);

    $this->actingAs($headUser);

    Livewire::test('pages::approvals')
        ->assertSee('Mine To Decide')
        ->assertDontSee('Someone Elses Queue');
});

test('the section head can approve from the queue', function () {
    ['employee' => $employee, 'sectionHeadUser' => $headUser] = sectionWithHead();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);

    $this->actingAs($headUser);

    Livewire::test('pages::approvals')
        ->set('remarks', 'Endorsed.')
        ->call('approve', $record->id)
        ->assertHasNoErrors();

    expect($record->fresh()->current_level)->toBe(ApprovalLevel::DivisionHead);
});

test('the section head can reject with a reason', function () {
    ['employee' => $employee, 'sectionHeadUser' => $headUser] = sectionWithHead();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);

    $this->actingAs($headUser);

    Livewire::test('pages::approvals')
        ->set('remarks', 'Outside the training plan.')
        ->call('reject', $record->id)
        ->assertHasNoErrors();

    expect($record->fresh()->status)->toBe(TrainingStatus::Rejected)
        ->and($record->fresh()->rejection_reason)->toBe('Outside the training plan.');
});

test('rejecting without a reason is refused', function () {
    ['employee' => $employee, 'sectionHeadUser' => $headUser] = sectionWithHead();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);

    $this->actingAs($headUser);

    Livewire::test('pages::approvals')
        ->set('remarks', '')
        ->call('reject', $record->id)
        ->assertHasErrors('remarks');

    expect($record->fresh()->status)->toBe(TrainingStatus::Pending);
});

test('the head of another section cannot decide', function () {
    ['employee' => $employee] = sectionWithHead();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);

    // A head, but not this record's head.
    ['sectionHeadUser' => $stranger] = sectionWithHead();

    $this->actingAs($stranger);

    Livewire::test('pages::approvals')
        ->set('remarks', 'Approving anyway.')
        ->call('approve', $record->id)
        ->assertForbidden();
});

test('hr decides on a record that is sitting with a head', function () {
    ['employee' => $employee] = sectionWithHead();
    $record = TrainingRecord::factory()->for($employee)->create([
        'title' => 'Waiting On Somebody Else',
        'current_level' => ApprovalLevel::SectionHead,
    ]);

    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::approvals')
        ->assertSee('Waiting On Somebody Else')
        ->call('approve', $record->id)
        ->assertHasNoErrors();

    expect($record->fresh()->approvals)->toHaveCount(1);
});

test('hr sees the records that have no approver at all', function () {
    $section = Section::factory()->create(['section_head_employee_id' => null]);
    $employee = Employee::factory()->for($section)->create();
    TrainingRecord::factory()->for($employee)->create([
        'title' => 'Stuck Seminar',
        'current_level' => null,
    ]);

    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::approvals')->assertSee('Stuck Seminar');
});

test('opening the modal remembers the record and clears stale remarks', function () {
    ['employee' => $employee, 'sectionHeadUser' => $headUser] = sectionWithHead();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);

    $this->actingAs($headUser);

    Livewire::test('pages::approvals')
        ->set('remarks', 'Left over from last time.')
        ->call('startDecision', $record->id, 'reject')
        ->assertSet('decidingId', $record->id)
        ->assertSet('decisionType', 'reject')
        ->assertSet('remarks', '');
});

test('confirming the modal applies the chosen decision', function () {
    ['employee' => $employee, 'sectionHeadUser' => $headUser] = sectionWithHead();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);

    $this->actingAs($headUser);

    Livewire::test('pages::approvals')
        ->call('startDecision', $record->id, 'reject')
        ->set('remarks', 'Not in the plan.')
        ->call('confirmDecision')
        ->assertHasNoErrors();

    expect($record->fresh()->status)->toBe(TrainingStatus::Rejected);
});

test('hr sees every record awaiting a decision, whoever holds it', function () {
    ['employee' => $mine] = sectionWithHead();
    ['employee' => $theirs] = sectionWithHead();

    TrainingRecord::factory()->for($mine)->create([
        'title' => 'With A Section Head',
        'current_level' => ApprovalLevel::SectionHead,
    ]);

    TrainingRecord::factory()->for($theirs)->create([
        'title' => 'With A Division Head',
        'current_level' => ApprovalLevel::DivisionHead,
    ]);

    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::approvals')
        ->assertSee('With A Section Head')
        ->assertSee('With A Division Head')
        ->assertSee('Waiting on');
});

test('a head still sees only what is waiting on them', function () {
    ['employee' => $mine, 'sectionHeadUser' => $headUser] = sectionWithHead();
    ['employee' => $theirs] = sectionWithHead();

    TrainingRecord::factory()->for($mine)->create([
        'title' => 'Mine To Decide',
        'current_level' => ApprovalLevel::SectionHead,
    ]);

    TrainingRecord::factory()->for($theirs)->create([
        'title' => 'Somebody Elses',
        'current_level' => ApprovalLevel::SectionHead,
    ]);

    $this->actingAs($headUser);

    Livewire::test('pages::approvals')
        ->assertSee('Mine To Decide')
        ->assertDontSee('Somebody Elses')
        ->assertDontSee('Waiting on');
});

test('hr decides on a record nobody could approve, from the screen', function () {
    $section = Section::factory()->create(['section_head_employee_id' => null]);
    $employee = Employee::factory()->for($section)->create();
    $record = TrainingRecord::factory()->for($employee)->create([
        'title' => 'Stuck Seminar',
        'current_level' => null,
    ]);

    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::approvals')
        ->call('approve', $record->id)
        ->assertHasNoErrors();

    expect($record->fresh()->status)->toBe(TrainingStatus::Approved);
});
