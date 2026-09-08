<?php

use App\Models\Eligibility;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config()->set('ldi.legacy_connection', config('database.default'));
    config()->set('ldi.legacy_tables', [
        'eligibilities' => 'legacy_eligibilities',
        'employees' => 'legacy_employees',
    ]);

    Schema::create('legacy_eligibilities', function ($table) {
        $table->id();
        $table->string('eligibility_name');
    });

    Schema::create('legacy_employees', function ($table) {
        $table->id();
        $table->string('firstname');
        $table->string('lastname');
        $table->unsignedBigInteger('eligibility_id')->nullable();
        $table->string('eligibility')->nullable();
        $table->date('expiry_date')->nullable();
    });

    DB::table('legacy_eligibilities')->insert([
        ['id' => 5, 'eligibility_name' => 'RA 1080'],
        ['id' => 15, 'eligibility_name' => 'CSP - Career Service Professional'],
    ]);
});

test('it imports the eligibility types', function () {
    $this->artisan('ldi:import-eligibilities')->assertSuccessful();

    expect(Eligibility::count())->toBe(2)
        ->and(Eligibility::where('name', 'RA 1080')->exists())->toBeTrue();
});

test('it attaches the eligibility and its expiry to the matching employee', function () {
    $employee = Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Cruz']);

    DB::table('legacy_employees')->insert([
        ['firstname' => 'Maria', 'lastname' => 'Cruz', 'eligibility_id' => 5, 'eligibility' => 'PRC License', 'expiry_date' => '2027-04-04'],
    ]);

    $this->artisan('ldi:import-eligibilities')->assertSuccessful();

    $employee->refresh();

    expect($employee->eligibility->name)->toBe('RA 1080')
        ->and($employee->eligibility_detail)->toBe('PRC License')
        ->and($employee->eligibility_expires_on->toDateString())->toBe('2027-04-04');
});

test('it matches despite a name suffix living in the source first name', function () {
    $employee = Employee::factory()->create([
        'first_name' => 'Carmelito',
        'last_name' => 'Bongato',
        'suffix' => 'Jr.',
    ]);

    DB::table('legacy_employees')->insert([
        ['firstname' => 'CARMELITO JR', 'lastname' => 'BONGATO', 'eligibility_id' => 15, 'eligibility' => null, 'expiry_date' => null],
    ]);

    $this->artisan('ldi:import-eligibilities')->assertSuccessful();

    expect($employee->refresh()->eligibility->name)->toBe('CSP - Career Service Professional');
});

test('an eligibility that never lapses keeps a null expiry', function () {
    $employee = Employee::factory()->create(['first_name' => 'Jose', 'last_name' => 'Rizal']);

    DB::table('legacy_employees')->insert([
        ['firstname' => 'Jose', 'lastname' => 'Rizal', 'eligibility_id' => 15, 'eligibility' => null, 'expiry_date' => null],
    ]);

    $this->artisan('ldi:import-eligibilities')->assertSuccessful();

    expect($employee->refresh()->eligibility_expires_on)->toBeNull();
});

test('running it twice changes nothing', function () {
    Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Cruz']);

    DB::table('legacy_employees')->insert([
        ['firstname' => 'Maria', 'lastname' => 'Cruz', 'eligibility_id' => 5, 'eligibility' => 'PRC License', 'expiry_date' => '2027-04-04'],
    ]);

    $this->artisan('ldi:import-eligibilities')->assertSuccessful();
    $this->artisan('ldi:import-eligibilities')->assertSuccessful();

    expect(Eligibility::count())->toBe(2)
        ->and(Employee::count())->toBe(1);
});

test('a source row with nobody to match is reported, not fatal', function () {
    DB::table('legacy_employees')->insert([
        ['firstname' => 'Ghost', 'lastname' => 'Employee', 'eligibility_id' => 5, 'eligibility' => null, 'expiry_date' => null],
    ]);

    $this->artisan('ldi:import-eligibilities')
        ->expectsOutputToContain('No local employee matched "Ghost Employee".')
        ->assertSuccessful();
});
