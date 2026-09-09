<?php

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use App\Enums\LdType;
use App\Enums\TrainingStatus;
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

    Livewire::test('pages::trainings.form-modal')
        ->call('add')
        ->set(formFields($employee->id))
        ->call('save')
        ->assertHasNoErrors();

    expect(TrainingRecord::where('employee_id', $employee->id)->count())->toBe(1);
});

test('an other type must carry its own text', function () {
    $employee = actingAsEmployee();

    Livewire::test('pages::trainings.form-modal')
        ->call('add')
        ->set(formFields($employee->id))
        ->set('ld_type', LdType::Other->value)
        ->set('ld_type_other', '')
        ->call('save')
        ->assertHasErrors('ld_type_other');
});

test('the end date cannot come before the start date', function () {
    $employee = actingAsEmployee();

    Livewire::test('pages::trainings.form-modal')
        ->call('add')
        ->set(formFields($employee->id))
        ->set('date_start', '2026-03-04')
        ->set('date_end', '2026-03-02')
        ->call('save')
        ->assertHasErrors('date_end');
});

test('hr can record a training for somebody else', function () {
    $this->actingAs(User::factory()->hr()->create());
    $employee = Employee::factory()->create();

    Livewire::test('pages::trainings.form-modal')
        ->call('add')
        ->set(formFields($employee->id))
        ->call('save')
        ->assertHasNoErrors();

    expect(TrainingRecord::where('employee_id', $employee->id)->count())->toBe(1);
});

test('an employee cannot record a training for somebody else', function () {
    actingAsEmployee();
    $other = Employee::factory()->create();

    Livewire::test('pages::trainings.form-modal')
        ->call('add')
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

    Livewire::test('pages::trainings.form-modal')
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

    Livewire::test('pages::trainings.form-modal')
        ->call('edit', $record->id)
        ->assertForbidden();
});

test('an approved record can no longer be corrected', function () {
    $employee = actingAsEmployee();
    $record = TrainingRecord::factory()->for($employee)->approved()->create();

    Livewire::test('pages::trainings.form-modal')
        ->call('edit', $record->id)
        ->assertForbidden();
});

test('an employee cannot correct somebody elses record', function () {
    actingAsEmployee();
    $record = TrainingRecord::factory()->create();

    Livewire::test('pages::trainings.form-modal')
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

    Livewire::test('pages::trainings.form-modal')
        ->call('edit', $record->id)
        ->set('employeeId', $moved->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($record->fresh()->employee_id)->toBe($moved->id)
        ->and($record->fresh()->current_level)->toBe(ApprovalLevel::SectionHead);
});

test('hr records a training from an employee profile', function () {
    $this->actingAs(User::factory()->hr()->create());
    $employee = Employee::factory()->create();

    Livewire::test('pages::trainings.form-modal')
        ->call('add', $employee->id)
        ->assertSet('employeeId', $employee->id)
        ->assertSet('fixedEmployeeId', $employee->id)
        // Fixed to one person, so the picker is not offered.
        ->assertSet('canChooseEmployee', false)
        ->set(formFields($employee->id))
        ->call('save')
        ->assertHasNoErrors();

    expect(TrainingRecord::where('employee_id', $employee->id)->count())->toBe(1);
});

test('the profile offers the button to hr but not to a head over their own people', function () {
    $section = Section::factory()->create();

    $headUser = User::factory()->sectionHead()->create();
    $head = Employee::factory()->for($section)->create(['user_id' => $headUser->id]);
    $section->update(['section_head_employee_id' => $head->id]);

    $employee = Employee::factory()->for($section)->create();

    $this->actingAs(User::factory()->hr()->create());
    $this->get(route('employees.show', $employee))->assertOk()->assertSee('Add training');

    // A head decides on their people's records; they do not write them.
    $this->actingAs($headUser);
    $this->get(route('employees.show', $employee))->assertOk()->assertDontSee('Add training');
});

test('a record hr adds still goes to the head for a decision', function () {
    $section = Section::factory()->create();
    $head = Employee::factory()->for($section)->create();
    $section->update(['section_head_employee_id' => $head->id]);

    $employee = Employee::factory()->for($section)->create();

    $this->actingAs($user = User::factory()->hr()->create());

    Livewire::test('pages::trainings.form-modal')
        ->call('add', $employee->id)
        ->set(formFields($employee->id))
        ->call('save');

    $record = TrainingRecord::where('employee_id', $employee->id)->firstOrFail();

    // Recording it is not deciding on it, and the trail names who typed it.
    expect($record->status)->toBe(TrainingStatus::Pending)
        ->and($record->current_level)->toBe(ApprovalLevel::SectionHead)
        ->and($record->submitted_by)->toBe($user->id);
});

test('an employee cannot open the form fixed to somebody else', function () {
    actingAsEmployee();
    $other = Employee::factory()->create();

    Livewire::test('pages::trainings.form-modal')
        ->call('add', $other->id)
        ->set(formFields($other->id))
        ->call('save')
        ->assertForbidden();
});

test('saving tells the list behind the modal to redraw', function () {
    $employee = actingAsEmployee();

    Livewire::test('pages::trainings.form-modal')
        ->call('add')
        ->set(formFields($employee->id))
        ->call('save')
        ->assertDispatched('training-saved');
});
