<?php

use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Point the importer at the test connection itself. Copying the config
    // would not do: each sqlite :memory: connection is its own database, so
    // the fixture tables would be invisible to it.
    config()->set('ldi.hris_connection', config('database.default'));
    config()->set('ldi.hris_tables', [
        'divisions' => 'hris_divisions',
        'sections' => 'hris_sections',
        'positions' => 'hris_positions',
        'employees' => 'hris_employees',
    ]);

    Schema::create('hris_divisions', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('code');
        $table->unsignedBigInteger('division_head_employee_id')->nullable();
    });

    Schema::create('hris_sections', function ($table) {
        $table->id();
        $table->unsignedBigInteger('division_id');
        $table->string('name');
        $table->string('code');
        $table->unsignedBigInteger('section_head_employee_id')->nullable();
    });

    Schema::create('hris_positions', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('item_number')->nullable();
        $table->unsignedTinyInteger('salary_grade')->nullable();
    });

    Schema::create('hris_employees', function ($table) {
        $table->id();
        $table->string('employee_number');
        $table->string('first_name');
        $table->string('middle_name')->nullable();
        $table->string('last_name');
        $table->string('suffix')->nullable();
        $table->unsignedBigInteger('position_id')->nullable();
        $table->unsignedBigInteger('section_id')->nullable();
        $table->unsignedBigInteger('division_id')->nullable();
        $table->date('date_hired')->nullable();
        $table->string('employment_status');
        $table->boolean('is_active')->default(true);
        $table->timestamp('deleted_at')->nullable();
    });

    DB::table('hris_divisions')->insert([
        ['id' => 1, 'name' => 'Finance Division', 'code' => 'FAD', 'division_head_employee_id' => 2],
    ]);
    DB::table('hris_sections')->insert([
        ['id' => 1, 'division_id' => 1, 'name' => 'Human Resource', 'code' => 'HRS', 'section_head_employee_id' => null],
    ]);
    DB::table('hris_positions')->insert([
        ['id' => 1, 'title' => 'Administrative Officer V', 'item_number' => 'ITEM-1', 'salary_grade' => 18],
    ]);
    DB::table('hris_employees')->insert([
        ['id' => 1, 'employee_number' => 'EMP-001', 'first_name' => 'Maria', 'middle_name' => null, 'last_name' => 'Cruz', 'suffix' => null, 'position_id' => 1, 'section_id' => 1, 'division_id' => 1, 'date_hired' => '2015-01-05', 'employment_status' => 'permanent', 'is_active' => true, 'deleted_at' => null],
        ['id' => 2, 'employee_number' => 'EMP-002', 'first_name' => 'Jose', 'middle_name' => null, 'last_name' => 'Rizal', 'suffix' => null, 'position_id' => 1, 'section_id' => 1, 'division_id' => 1, 'date_hired' => '2010-03-01', 'employment_status' => 'permanent', 'is_active' => true, 'deleted_at' => null],
    ]);
});

test('it imports the organisation and the employees', function () {
    $this->artisan('ldi:import-employees')->assertSuccessful();

    expect(Division::count())->toBe(1)
        ->and(Section::count())->toBe(1)
        ->and(Position::count())->toBe(1)
        ->and(Employee::count())->toBe(2)
        ->and(Employee::where('employee_number', 'EMP-001')->first()->full_name)->toBe('Maria Cruz');
});

test('it wires the employee to its section, division and position', function () {
    $this->artisan('ldi:import-employees')->assertSuccessful();

    $employee = Employee::where('employee_number', 'EMP-001')->first();

    expect($employee->section->code)->toBe('HRS')
        ->and($employee->division->code)->toBe('FAD')
        ->and($employee->position->title)->toBe('Administrative Officer V');
});

test('it sets the head designations after the employees exist', function () {
    $this->artisan('ldi:import-employees')->assertSuccessful();

    $head = Employee::where('employee_number', 'EMP-002')->first();

    expect(Division::first()->division_head_employee_id)->toBe($head->id);
});

test('running it twice changes nothing', function () {
    $this->artisan('ldi:import-employees')->assertSuccessful();
    $this->artisan('ldi:import-employees')->assertSuccessful();

    expect(Employee::count())->toBe(2)
        ->and(Division::count())->toBe(1);
});

test('it updates an employee whose details changed at source', function () {
    $this->artisan('ldi:import-employees')->assertSuccessful();

    DB::table('hris_employees')->where('employee_number', 'EMP-001')->update(['last_name' => 'Santos']);
    $this->artisan('ldi:import-employees')->assertSuccessful();

    expect(Employee::where('employee_number', 'EMP-001')->first()->last_name)->toBe('Santos')
        ->and(Employee::count())->toBe(2);
});
