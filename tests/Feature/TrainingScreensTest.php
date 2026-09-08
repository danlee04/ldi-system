<?php

use App\Enums\ApprovalLevel;
use App\Enums\LdType;
use App\Models\Employee;
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

test('an employee can record a training for themselves', function () {
    $employee = actingAsEmployee();

    Livewire::test('pages::trainings.form')
        ->set('employeeId', $employee->id)
        ->set('title', 'Records Management Seminar')
        ->set('date_start', '2026-03-02')
        ->set('date_end', '2026-03-04')
        ->set('hours', 24)
        ->set('ld_type', LdType::Technical->value)
        ->set('conducted_by', 'Civil Service Commission')
        ->call('save')
        ->assertHasNoErrors();

    expect(TrainingRecord::where('employee_id', $employee->id)->count())->toBe(1);
});

test('an other type must carry its own text', function () {
    $employee = actingAsEmployee();

    Livewire::test('pages::trainings.form')
        ->set('employeeId', $employee->id)
        ->set('title', 'Annual Convention')
        ->set('date_start', '2026-03-02')
        ->set('date_end', '2026-03-04')
        ->set('hours', 8)
        ->set('ld_type', LdType::Other->value)
        ->set('ld_type_other', '')
        ->set('conducted_by', 'PHA')
        ->call('save')
        ->assertHasErrors('ld_type_other');
});

test('the end date cannot come before the start date', function () {
    $employee = actingAsEmployee();

    Livewire::test('pages::trainings.form')
        ->set('employeeId', $employee->id)
        ->set('title', 'Records Management Seminar')
        ->set('date_start', '2026-03-04')
        ->set('date_end', '2026-03-02')
        ->set('hours', 24)
        ->set('ld_type', LdType::Technical->value)
        ->set('conducted_by', 'Civil Service Commission')
        ->call('save')
        ->assertHasErrors('date_end');
});

test('hr can record a training for somebody else', function () {
    $this->actingAs(User::factory()->hr()->create());
    $employee = Employee::factory()->create();

    Livewire::test('pages::trainings.form')
        ->set('employeeId', $employee->id)
        ->set('title', 'Records Management Seminar')
        ->set('date_start', '2026-03-02')
        ->set('date_end', '2026-03-04')
        ->set('hours', 24)
        ->set('ld_type', LdType::Technical->value)
        ->set('conducted_by', 'Civil Service Commission')
        ->call('save')
        ->assertHasNoErrors();

    expect(TrainingRecord::where('employee_id', $employee->id)->count())->toBe(1);
});

test('an employee cannot record a training for somebody else', function () {
    actingAsEmployee();
    $other = Employee::factory()->create();

    Livewire::test('pages::trainings.form')
        ->set('employeeId', $other->id)
        ->set('title', 'Records Management Seminar')
        ->set('date_start', '2026-03-02')
        ->set('date_end', '2026-03-04')
        ->set('hours', 24)
        ->set('ld_type', LdType::Technical->value)
        ->set('conducted_by', 'Civil Service Commission')
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

test('the detail page shows the approval trail', function () {
    $employee = actingAsEmployee();
    $record = TrainingRecord::factory()->for($employee)->create();
    TrainingApproval::factory()->for($record)->create([
        'level' => ApprovalLevel::SectionHead,
        'remarks' => 'Endorsed by the section.',
    ]);

    $this->get(route('trainings.show', $record))
        ->assertOk()
        ->assertSee('Endorsed by the section.');
});
