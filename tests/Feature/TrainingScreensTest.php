<?php

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use App\Enums\LdType;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\TrainingApproval;
use App\Models\TrainingRecord;
use App\Models\User;
use Livewire\Livewire;

function actingAsEmployee(): Employee
{
    $user = User::factory()->employee()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    test()->actingAs($user);

    return $employee;
}

/**
 * The fields the form modal needs to accept a submission.
 *
 * @return array<string, mixed>
 */
function formFields(int $employeeId): array
{
    return [
        'employeeId' => $employeeId,
        'title' => 'Records Management Seminar',
        'date_start' => '2026-03-02',
        'date_end' => '2026-03-04',
        'hours' => 24,
        'ld_type' => LdType::Technical->value,
        'conducted_by' => 'Civil Service Commission',
    ];
}

test('an employee can record a training for themselves', function () {
    $employee = actingAsEmployee();

    Livewire::test('pages::trainings.mine')
        ->call('create')
        ->set(formFields($employee->id))
        ->call('save')
        ->assertHasNoErrors();

    expect(TrainingRecord::where('employee_id', $employee->id)->count())->toBe(1);
});

test('an other type must carry its own text', function () {
    $employee = actingAsEmployee();

    Livewire::test('pages::trainings.mine')
        ->call('create')
        ->set(formFields($employee->id))
        ->set('ld_type', LdType::Other->value)
        ->set('ld_type_other', '')
        ->call('save')
        ->assertHasErrors('ld_type_other');
});

test('the end date cannot come before the start date', function () {
    $employee = actingAsEmployee();

    Livewire::test('pages::trainings.mine')
        ->call('create')
        ->set(formFields($employee->id))
        ->set('date_start', '2026-03-04')
        ->set('date_end', '2026-03-02')
        ->call('save')
        ->assertHasErrors('date_end');
});

test('hr can record a training for somebody else', function () {
    $this->actingAs(User::factory()->hr()->create());
    $employee = Employee::factory()->create();

    Livewire::test('pages::trainings.mine')
        ->call('create')
        ->set(formFields($employee->id))
        ->call('save')
        ->assertHasNoErrors();

    expect(TrainingRecord::where('employee_id', $employee->id)->count())->toBe(1);
});

test('an employee cannot record a training for somebody else', function () {
    actingAsEmployee();
    $other = Employee::factory()->create();

    Livewire::test('pages::trainings.mine')
        ->call('create')
        ->set(formFields($other->id))
        ->call('save')
        ->assertForbidden();
});

test('my trainings lists only my own records', function () {
    $employee = actingAsEmployee();
    TrainingRecord::factory()->for($employee)->create(['title' => 'Mine Seminar']);
    TrainingRecord::factory()->create(['title' => 'Somebody Else Seminar']);

    Livewire::test('pages::trainings.mine')
        ->assertSee('Mine Seminar')
        ->assertDontSee('Somebody Else Seminar');
});

test('the detail modal shows the approval trail', function () {
    $employee = actingAsEmployee();
    $record = TrainingRecord::factory()->for($employee)->create();
    TrainingApproval::factory()->for($record)->create([
        'level' => ApprovalLevel::SectionHead,
        'remarks' => 'Endorsed by the section.',
    ]);

    Livewire::test('pages::trainings.detail-modal')
        ->call('showTraining', $record->id)
        ->assertSet('recordId', $record->id)
        ->assertSee('Endorsed by the section.');
});

test('the detail modal refuses a record outside what you may see', function () {
    actingAsEmployee();
    $other = TrainingRecord::factory()->create();

    Livewire::test('pages::trainings.detail-modal')
        ->call('showTraining', $other->id)
        ->assertForbidden();
});

test('the detail modal shows nothing until a record is chosen', function () {
    actingAsEmployee();

    Livewire::test('pages::trainings.detail-modal')
        ->assertSet('recordId', null)
        ->assertDontSee('Approval trail');
});

test('an employee can correct a record nobody has acted on', function () {
    $employee = actingAsEmployee();
    $record = TrainingRecord::factory()->for($employee)->create(['title' => 'Wrong Title']);

    Livewire::test('pages::trainings.mine')
        ->call('edit', $record->id)
        ->assertSet('editingId', $record->id)
        ->assertSet('title', 'Wrong Title')
        ->set('title', 'Corrected Title')
        ->call('save')
        ->assertHasNoErrors();

    expect($record->fresh()->title)->toBe('Corrected Title')
        ->and(TrainingRecord::count())->toBe(1);
});

test('a record locks once somebody has decided on it', function () {
    $employee = actingAsEmployee();
    $record = TrainingRecord::factory()->for($employee)->create();
    TrainingApproval::factory()->for($record)->create([
        'level' => ApprovalLevel::SectionHead,
        'decision' => ApprovalDecision::Approved,
    ]);

    Livewire::test('pages::trainings.mine')
        ->call('edit', $record->id)
        ->assertForbidden();
});

test('an approved record can no longer be corrected', function () {
    $employee = actingAsEmployee();
    $record = TrainingRecord::factory()->for($employee)->approved()->create();

    Livewire::test('pages::trainings.mine')
        ->call('edit', $record->id)
        ->assertForbidden();
});

test('an employee cannot correct somebody elses record', function () {
    actingAsEmployee();
    $record = TrainingRecord::factory()->create();

    Livewire::test('pages::trainings.mine')
        ->call('edit', $record->id)
        ->assertForbidden();
});

test('editing reroutes the record when hr moves it to another employee', function () {
    $this->actingAs(User::factory()->hr()->create());
    $record = TrainingRecord::factory()->create(['current_level' => null]);

    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();
    $head = Employee::factory()->for($section)->create();
    $section->update(['section_head_employee_id' => $head->id]);
    $moved = Employee::factory()->for($section)->create();

    Livewire::test('pages::trainings.mine')
        ->call('edit', $record->id)
        ->set('employeeId', $moved->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($record->fresh()->employee_id)->toBe($moved->id)
        ->and($record->fresh()->current_level)->toBe(ApprovalLevel::SectionHead);
});
