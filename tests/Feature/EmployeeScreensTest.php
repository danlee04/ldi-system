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

test('an employee cannot open somebody elses profile', function () {
    $user = User::factory()->employee()->create();
    Employee::factory()->create(['user_id' => $user->id]);
    $other = Employee::factory()->create();

    $this->actingAs($user);

    $this->get(route('employees.show', $other))->assertForbidden();
});
