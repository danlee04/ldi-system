<?php

use App\Enums\UserRole;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config()->set('ldi.hris_connection', config('database.default'));
    config()->set('ldi.hris_tables', [
        'employees' => 'hris_employees',
        'users' => 'hris_users',
    ]);

    Schema::create('hris_users', function ($table) {
        $table->id();
        $table->string('email');
        $table->string('password');
    });

    Schema::create('hris_employees', function ($table) {
        $table->id();
        $table->string('employee_number');
        $table->unsignedBigInteger('user_id');
        $table->boolean('is_hr_officer')->default(false);
        $table->timestamp('deleted_at')->nullable();
    });
});

/**
 * Adds one account on the source side.
 */
function sourceAccount(string $employeeNumber, string $email, bool $isHrOfficer = false): void
{
    $userId = DB::table('hris_users')->insertGetId([
        'email' => $email,
        'password' => Hash::make('their-hris-password'),
    ]);

    DB::table('hris_employees')->insert([
        'employee_number' => $employeeNumber,
        'user_id' => $userId,
        'is_hr_officer' => $isHrOfficer,
        'deleted_at' => null,
    ]);
}

test('it creates a sign-in and links it to the employee', function () {
    $employee = Employee::factory()->create(['employee_number' => 'EMP-001', 'user_id' => null]);
    sourceAccount('EMP-001', 'maria@example.test');

    $this->artisan('ldi:import-user-accounts')->assertSuccessful();

    $employee->refresh();

    expect(User::where('email', 'maria@example.test')->exists())->toBeTrue()
        ->and($employee->user_id)->toBe(User::where('email', 'maria@example.test')->value('id'))
        ->and($employee->user->name)->toBe($employee->full_name);
});

test('the imported account keeps the password people already use', function () {
    Employee::factory()->create(['employee_number' => 'EMP-001']);
    sourceAccount('EMP-001', 'maria@example.test');

    $this->artisan('ldi:import-user-accounts')->assertSuccessful();

    expect(Hash::check('their-hris-password', User::where('email', 'maria@example.test')->value('password')))
        ->toBeTrue();
});

test('a section head is given the section head role', function () {
    $section = Section::factory()->create();
    $head = Employee::factory()->for($section)->create(['employee_number' => 'EMP-001']);
    $section->update(['section_head_employee_id' => $head->id]);

    sourceAccount('EMP-001', 'head@example.test');

    $this->artisan('ldi:import-user-accounts')->assertSuccessful();

    expect(User::where('email', 'head@example.test')->value('role'))->toBe(UserRole::SectionHead);
});

test('a division head is given the division head role', function () {
    $division = Division::factory()->create();
    $head = Employee::factory()->for(Section::factory()->for($division))->create(['employee_number' => 'EMP-001']);
    $division->update(['division_head_employee_id' => $head->id]);

    sourceAccount('EMP-001', 'head@example.test');

    $this->artisan('ldi:import-user-accounts')->assertSuccessful();

    expect(User::where('email', 'head@example.test')->value('role'))->toBe(UserRole::DivisionHead);
});

test('the hr officer is hr even when they also head a section', function () {
    $section = Section::factory()->create();
    $head = Employee::factory()->for($section)->create(['employee_number' => 'EMP-001']);
    $section->update(['section_head_employee_id' => $head->id]);

    sourceAccount('EMP-001', 'hr@example.test', isHrOfficer: true);

    $this->artisan('ldi:import-user-accounts')->assertSuccessful();

    expect(User::where('email', 'hr@example.test')->value('role'))->toBe(UserRole::Hr);
});

test('everybody else is a plain employee', function () {
    Employee::factory()->create(['employee_number' => 'EMP-001']);
    sourceAccount('EMP-001', 'plain@example.test');

    $this->artisan('ldi:import-user-accounts')->assertSuccessful();

    expect(User::where('email', 'plain@example.test')->value('role'))->toBe(UserRole::Employee);
});

test('running it twice creates nothing new', function () {
    Employee::factory()->create(['employee_number' => 'EMP-001']);
    sourceAccount('EMP-001', 'maria@example.test');

    $this->artisan('ldi:import-user-accounts')->assertSuccessful();
    $this->artisan('ldi:import-user-accounts')->assertSuccessful();

    expect(User::where('email', 'maria@example.test')->count())->toBe(1);
});

test('a source account with no local employee is reported, not fatal', function () {
    sourceAccount('EMP-999', 'ghost@example.test');

    $this->artisan('ldi:import-user-accounts')
        ->expectsOutputToContain('has no local employee')
        ->assertSuccessful();

    expect(User::where('email', 'ghost@example.test')->exists())->toBeFalse();
});
