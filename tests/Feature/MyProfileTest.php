<?php

use App\Actions\Pds\PersonalDataSheetProgress;
use App\Models\Employee;
use App\Models\EmployeeEducation;
use App\Models\EmployeeEligibility;
use App\Models\PersonalDataSheet;
use App\Models\Section;
use App\Models\TrainingRecord;
use App\Models\User;
use Livewire\Livewire;

/**
 * Signs in an employee and hands back their record.
 */
function profileEmployee(array $attributes = []): Employee
{
    $user = User::factory()->employee()->create();
    $employee = Employee::factory()->create([...$attributes, 'user_id' => $user->id]);

    test()->actingAs($user);

    return $employee;
}

test('an employee sees their own details', function () {
    $section = Section::factory()->create(['name' => 'Human Resource Development Section']);

    profileEmployee([
        'first_name' => 'Maria',
        'last_name' => 'Cruz',
        'employee_number' => 'EMP-0042',
        'section_id' => $section->id,
    ]);

    $this->get(route('my-profile'))
        ->assertOk()
        ->assertSee('Maria')
        ->assertSee('EMP-0042')
        ->assertSee('Human Resource Development Section');
});

test('an account with no employee record cannot open it', function () {
    $this->actingAs(User::factory()->hr()->create());

    $this->get(route('my-profile'))->assertForbidden();
});

test('nobody else profile is reachable from here', function () {
    profileEmployee(['last_name' => 'Mine']);

    Employee::factory()->create(['last_name' => 'Somebody Else']);

    Livewire::test('pages::my-profile')->assertDontSee('Somebody Else');
});

test('the cpd units count only approved training from this year', function () {
    $employee = profileEmployee();

    TrainingRecord::factory()->for($employee)->approved()->create([
        'cpd_units' => 12,
        'date_end' => now()->startOfYear()->addMonth(),
    ]);

    // Approved, but last year.
    TrainingRecord::factory()->for($employee)->approved()->create([
        'cpd_units' => 8,
        'date_end' => now()->subYear(),
    ]);

    // This year, but nobody has decided on it.
    TrainingRecord::factory()->for($employee)->create([
        'cpd_units' => 5,
        'date_end' => now()->startOfYear()->addMonths(2),
    ]);

    expect(Livewire::test('pages::my-profile')->instance()->cpdUnits)->toBe(12.0);
});

test('the training history shows every one of theirs, decided or not', function () {
    $employee = profileEmployee();

    TrainingRecord::factory()->for($employee)->approved()->create(['title' => 'Records Management']);
    TrainingRecord::factory()->for($employee)->create(['title' => 'Still Waiting']);

    Livewire::test('pages::my-profile')
        ->assertSee('Records Management')
        ->assertSee('Still Waiting');
});

test('their eligibility is listed', function () {
    $employee = profileEmployee();

    EmployeeEligibility::factory()->for($employee)->create([
        'detail' => 'CSP - Career Service Professional',
        'rating' => '86.45',
    ]);

    Livewire::test('pages::my-profile')
        ->assertSee('CSP - Career Service Professional')
        ->assertSee('86.45');
});

test('an empty pds reports every section as still to do', function () {
    $employee = profileEmployee();

    $progress = app(PersonalDataSheetProgress::class);
    $sections = $progress->handle($employee);

    expect($sections)->toHaveCount(8)
        ->and($progress->percentage($sections))->toBe(0)
        ->and(collect($sections)->every(fn (array $section): bool => ! $section['filled']))->toBeTrue();
});

test('a section counts as done once it has anything in it', function () {
    $employee = profileEmployee();

    PersonalDataSheet::factory()->create([
        'employee_id' => $employee->id,
        'father_last_name' => 'Santos',
        'convicted_of_crime' => false,
    ]);

    EmployeeEducation::factory()->create(['employee_id' => $employee->id]);

    $sections = collect(app(PersonalDataSheetProgress::class)->handle($employee->fresh()))
        ->keyBy('number');

    expect($sections['I']['filled'])->toBeTrue()
        ->and($sections['II']['filled'])->toBeTrue()
        ->and($sections['III']['filled'])->toBeTrue()
        ->and($sections['IV']['filled'])->toBeFalse()
        // A no is an answer, so page 4 has been started.
        ->and($sections['34-41']['filled'])->toBeTrue();
});

test('the page names the sections still empty', function () {
    profileEmployee();

    Livewire::test('pages::my-profile')
        ->assertSee('Civil service eligibility')
        ->assertSee('Work experience')
        ->assertSee('0%');
});

test('learning and development is not something they are asked to fill', function () {
    $employee = profileEmployee();

    TrainingRecord::factory()->for($employee)->approved()->create();

    $numbers = collect(app(PersonalDataSheetProgress::class)->handle($employee))->pluck('number');

    expect($numbers)->not->toContain('VI')
        ->and(app(PersonalDataSheetProgress::class)->learningAndDevelopment($employee))->toBe(1);
});

test('an administrative account is not offered a profile in the sidebar', function () {
    $this->actingAs(User::factory()->hr()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('my-profile'))
        ->assertDontSee(route('my-pds'));
});

test('an employee is offered one', function () {
    profileEmployee();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('my-profile'));
});
