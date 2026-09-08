<?php

use App\Enums\EligibilityStatus;
use App\Enums\EmploymentStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\TrainingRecord;
use App\Models\User;
use Livewire\Livewire;

test('hr sees every active employee', function () {
    $this->actingAs(User::factory()->hr()->create());

    Employee::factory()->create(['last_name' => 'Bonifacio']);
    Employee::factory()->inactive()->create(['last_name' => 'Aguinaldo']);

    Livewire::test('pages::employees.index')
        ->assertSee('Bonifacio')
        ->assertDontSee('Aguinaldo');
});

test('a section head sees only their own section', function () {
    $division = Division::factory()->create();
    $ownSection = Section::factory()->for($division)->create();

    $headUser = User::factory()->sectionHead()->create();
    Employee::factory()->for($ownSection)->create(['user_id' => $headUser->id, 'last_name' => 'Mabini']);
    Employee::factory()->for(Section::factory()->create())->create(['last_name' => 'Jacinto']);

    $this->actingAs($headUser);

    Livewire::test('pages::employees.index')
        ->assertSee('Mabini')
        ->assertDontSee('Jacinto');
});

test('search narrows by name and employee number', function () {
    $this->actingAs(User::factory()->hr()->create());

    Employee::factory()->create(['last_name' => 'Bonifacio', 'employee_number' => 'EMP-111']);
    Employee::factory()->create(['last_name' => 'Jacinto', 'employee_number' => 'EMP-222']);

    Livewire::test('pages::employees.index')
        ->set('search', 'EMP-111')
        ->assertSee('Bonifacio')
        ->assertDontSee('Jacinto');
});

test('the division filter narrows the list', function () {
    $this->actingAs(User::factory()->hr()->create());

    $wanted = Division::factory()->create();
    Employee::factory()->for(Section::factory()->for($wanted)->create())->create(['last_name' => 'Bonifacio']);
    Employee::factory()->create(['last_name' => 'Jacinto']);

    Livewire::test('pages::employees.index')
        ->set('divisionId', $wanted->id)
        ->assertSee('Bonifacio')
        ->assertDontSee('Jacinto');
});

test('the employment status filter narrows the list', function () {
    $this->actingAs(User::factory()->hr()->create());

    Employee::factory()->create(['last_name' => 'Bonifacio', 'employment_status' => EmploymentStatus::JobOrder]);
    Employee::factory()->create(['last_name' => 'Jacinto', 'employment_status' => EmploymentStatus::Permanent]);

    Livewire::test('pages::employees.index')
        ->set('employmentStatus', EmploymentStatus::JobOrder->value)
        ->assertSee('Bonifacio')
        ->assertDontSee('Jacinto');
});

test('the eligibility filter finds only the ones lapsing within a year', function () {
    $this->actingAs(User::factory()->hr()->create());

    Employee::factory()->create(['last_name' => 'Bonifacio', 'eligibility_expires_on' => today()->addMonths(6)]);
    Employee::factory()->create(['last_name' => 'Jacinto', 'eligibility_expires_on' => today()->addYears(3)]);
    Employee::factory()->create(['last_name' => 'Mabini', 'eligibility_expires_on' => null]);
    Employee::factory()->create(['last_name' => 'Luna', 'eligibility_expires_on' => today()->subDay()]);

    Livewire::test('pages::employees.index')
        ->set('eligibilityStatus', EligibilityStatus::Expiring->value)
        ->assertSee('Bonifacio')
        ->assertDontSee('Jacinto')
        ->assertDontSee('Mabini')
        ->assertDontSee('Luna');
});

test('the eligibility filter can single out the expired ones', function () {
    $this->actingAs(User::factory()->hr()->create());

    Employee::factory()->create(['last_name' => 'Bonifacio', 'eligibility_expires_on' => today()->subDay()]);
    Employee::factory()->create(['last_name' => 'Jacinto', 'eligibility_expires_on' => today()->addMonths(6)]);

    Livewire::test('pages::employees.index')
        ->set('eligibilityStatus', EligibilityStatus::Expired->value)
        ->assertSee('Bonifacio')
        ->assertDontSee('Jacinto');
});

test('the cpd column counts only approved training from this year', function () {
    $this->actingAs(User::factory()->hr()->create());

    $employee = Employee::factory()->create();

    TrainingRecord::factory()->for($employee)->approved()->create([
        'cpd_units' => 5,
        'date_end' => today(),
    ]);
    TrainingRecord::factory()->for($employee)->approved()->create([
        'cpd_units' => 9,
        'date_end' => today()->subYear(),
    ]);
    TrainingRecord::factory()->for($employee)->create([
        'cpd_units' => 7,
        'date_end' => today(),
    ]);

    $rows = Livewire::test('pages::employees.index')->instance()->employees;

    expect((float) $rows->first()->cpd_units_for_year)->toBe(5.0);
});

test('the profile lists the training history', function () {
    $this->actingAs(User::factory()->hr()->create());

    $employee = Employee::factory()->create();
    TrainingRecord::factory()->for($employee)->approved()->create(['title' => 'Records Management Seminar']);

    $this->get(route('employees.show', $employee))
        ->assertOk()
        ->assertSee('Records Management Seminar');
});

test('hr can add an employee', function () {
    $this->actingAs(User::factory()->hr()->create());

    $section = Section::factory()->create();

    Livewire::test('pages::employees.index')
        ->call('createEmployee')
        ->assertSet('editingId', null)
        ->set('employee_number', 'EMP-500')
        ->set('first_name', 'Maria')
        ->set('last_name', 'Cruz')
        ->set('employeeSectionId', $section->id)
        ->set('employment_status', EmploymentStatus::Permanent->value)
        ->call('saveEmployee')
        ->assertHasNoErrors();

    $employee = Employee::where('employee_number', 'EMP-500')->first();

    expect($employee)->not->toBeNull()
        ->and($employee->full_name)->toBe('Maria Cruz')
        ->and($employee->section_id)->toBe($section->id)
        ->and($employee->division_id)->toBe($section->division_id)
        ->and($employee->is_active)->toBeTrue();
});

test('a new employee cannot take an existing employee number', function () {
    $this->actingAs(User::factory()->hr()->create());

    Employee::factory()->create(['employee_number' => 'EMP-500']);

    Livewire::test('pages::employees.index')
        ->call('createEmployee')
        ->set('employee_number', 'EMP-500')
        ->set('first_name', 'Maria')
        ->set('last_name', 'Cruz')
        ->set('employment_status', EmploymentStatus::Permanent->value)
        ->call('saveEmployee')
        ->assertHasErrors('employee_number');
});

test('opening the add form does not carry over the last edited employee', function () {
    $this->actingAs(User::factory()->hr()->create());

    $employee = Employee::factory()->create(['first_name' => 'Jose', 'employee_number' => 'EMP-001']);

    Livewire::test('pages::employees.index')
        ->call('editEmployee', $employee->id)
        ->assertSet('first_name', 'Jose')
        ->call('createEmployee')
        ->assertSet('editingId', null)
        ->assertSet('first_name', '')
        ->assertSet('employee_number', '');
});

test('a section head cannot add an employee', function () {
    $this->actingAs(User::factory()->sectionHead()->create());

    Livewire::test('pages::employees.index')
        ->call('createEmployee')
        ->assertForbidden();
});

test('hr can correct an employee record', function () {
    $this->actingAs(User::factory()->hr()->create());

    $employee = Employee::factory()->create(['last_name' => 'Bonifacio']);
    $section = Section::factory()->create();

    Livewire::test('pages::employees.index')
        ->call('editEmployee', $employee->id)
        ->assertSet('last_name', 'Bonifacio')
        ->set('last_name', 'Del Pilar')
        ->set('employeeSectionId', $section->id)
        ->set('eligibility_expires_on', '2029-05-01')
        ->call('saveEmployee')
        ->assertHasNoErrors();

    $employee->refresh();

    expect($employee->last_name)->toBe('Del Pilar')
        ->and($employee->section_id)->toBe($section->id)
        ->and($employee->division_id)->toBe($section->division_id)
        ->and($employee->eligibility_expires_on->toDateString())->toBe('2029-05-01');
});

test('an employee number cannot collide with another employee', function () {
    $this->actingAs(User::factory()->hr()->create());

    Employee::factory()->create(['employee_number' => 'EMP-111']);
    $employee = Employee::factory()->create(['employee_number' => 'EMP-222']);

    Livewire::test('pages::employees.index')
        ->call('editEmployee', $employee->id)
        ->set('employee_number', 'EMP-111')
        ->call('saveEmployee')
        ->assertHasErrors('employee_number');
});

test('hr can remove an employee without losing their training', function () {
    $this->actingAs(User::factory()->hr()->create());

    $employee = Employee::factory()->create(['last_name' => 'Bonifacio']);
    TrainingRecord::factory()->for($employee)->create();

    Livewire::test('pages::employees.index')
        ->call('confirmDelete', $employee->id)
        ->assertSet('deletingId', $employee->id)
        ->call('deleteEmployee');

    expect(Employee::count())->toBe(0)
        ->and(Employee::withTrashed()->count())->toBe(1)
        ->and(TrainingRecord::count())->toBe(1);
});

test('a section head gets no action column at all', function () {
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();

    $headUser = User::factory()->sectionHead()->create();
    Employee::factory()->for($section)->create(['user_id' => $headUser->id]);

    $this->actingAs($headUser);

    Livewire::test('pages::employees.index')
        ->assertSet('canManage', false)
        ->assertDontSee('Delete');
});

test('a section head cannot edit or remove anybody', function () {
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();

    $headUser = User::factory()->sectionHead()->create();
    Employee::factory()->for($section)->create(['user_id' => $headUser->id]);
    $colleague = Employee::factory()->for($section)->create();

    $this->actingAs($headUser);

    Livewire::test('pages::employees.index')
        ->call('editEmployee', $colleague->id)
        ->assertForbidden();

    Livewire::test('pages::employees.index')
        ->call('confirmDelete', $colleague->id)
        ->assertForbidden();
});

test('an employee cannot open somebody elses profile', function () {
    $user = User::factory()->employee()->create();
    Employee::factory()->create(['user_id' => $user->id]);
    $other = Employee::factory()->create();

    $this->actingAs($user);

    $this->get(route('employees.show', $other))->assertForbidden();
});
