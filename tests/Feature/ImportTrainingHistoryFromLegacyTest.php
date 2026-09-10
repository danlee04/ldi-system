<?php

use App\Enums\ApprovalLevel;
use App\Enums\LdType;
use App\Enums\TrainingStatus;
use App\Models\Employee;
use App\Models\LdiTraining;
use App\Models\Section;
use App\Models\TrainingRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config()->set('ldi.legacy_connection', config('database.default'));

    Schema::create('legacy_employees', function ($table) {
        $table->id('employee_id');
        $table->string('firstname');
        $table->string('lastname');
        $table->string('gender')->nullable();
    });

    Schema::create('legacy_trainings', function ($table) {
        $table->id('training_id');
        $table->unsignedBigInteger('employee_id')->nullable();
        $table->string('training_title');
        $table->date('date_start');
        $table->date('date_end');
        $table->unsignedSmallInteger('training_hours');
        $table->string('type_of_ld')->nullable();
        $table->string('training_communication')->nullable();
        $table->string('facilitator')->nullable();
        $table->string('location')->nullable();
        $table->decimal('expenses', 10, 2)->nullable();
        $table->decimal('registration_fee', 10, 2)->nullable();
        $table->decimal('tev', 10, 2)->nullable();
        $table->float('cpd_units')->nullable();
        $table->string('status')->nullable();
        $table->text('rejection_reason')->nullable();
    });

    Schema::create('legacy_ldi_training', function ($table) {
        $table->id('ldi_id');
        $table->unsignedBigInteger('training_id');
        $table->string('training_title');
        $table->string('development_partner')->nullable();
        $table->string('type_of_training')->nullable();
        $table->string('target_attendees')->nullable();
        $table->decimal('budget', 12, 2)->nullable();
        $table->string('budget_source')->nullable();
    });

    // The importer reads fixed table names off the legacy connection, so the
    // fixtures are aliased through config the same way the other imports are.
    config()->set('ldi.legacy_tables', [
        'employees' => 'legacy_employees',
        'trainings' => 'legacy_trainings',
        'ldi_training' => 'legacy_ldi_training',
    ]);
});

test('it imports attendance and links it to the local employee', function () {
    $employee = Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Cruz']);
    User::factory()->hr()->create();

    DB::table('legacy_employees')->insert(['employee_id' => 7, 'firstname' => 'Maria', 'lastname' => 'Cruz']);
    DB::table('legacy_trainings')->insert([
        'training_id' => 1, 'employee_id' => 7, 'training_title' => 'Records Management Seminar',
        'date_start' => '2025-03-02', 'date_end' => '2025-03-04', 'training_hours' => 24,
        'type_of_ld' => 'Technical', 'facilitator' => 'Civil Service Commission',
        'registration_fee' => 1500, 'tev' => 2000, 'cpd_units' => 3, 'status' => 'approved',
    ]);

    $this->artisan('ldi:import-training-history')->assertSuccessful();

    $record = TrainingRecord::first();

    expect(TrainingRecord::count())->toBe(1)
        ->and($record->employee_id)->toBe($employee->id)
        ->and($record->status)->toBe(TrainingStatus::Approved)
        ->and($record->ld_type)->toBe(LdType::Technical)
        ->and($record->conducted_by)->toBe('Civil Service Commission')
        ->and((float) $record->registration_fee)->toBe(1500.0);
});

test('it brings sex across, because the DOH form counts by it', function () {
    $employee = Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Cruz', 'gender' => null]);
    User::factory()->hr()->create();

    DB::table('legacy_employees')->insert([
        'employee_id' => 7, 'firstname' => 'Maria', 'lastname' => 'Cruz', 'gender' => 'Female',
    ]);

    $this->artisan('ldi:import-training-history')->assertSuccessful();

    expect($employee->refresh()->gender)->toBe('Female');
});

test('it links attendance to the plan that shares its title', function () {
    Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Cruz']);
    User::factory()->hr()->create();

    DB::table('legacy_employees')->insert(['employee_id' => 7, 'firstname' => 'Maria', 'lastname' => 'Cruz']);

    DB::table('legacy_trainings')->insert([
        ['training_id' => 99, 'employee_id' => null, 'training_title' => 'Self-Defense Training',
            'date_start' => '2026-05-02', 'date_end' => '2026-05-04', 'training_hours' => 16,
            'type_of_ld' => 'Technical', 'facilitator' => 'DTRC', 'status' => 'approved'],
        ['training_id' => 100, 'employee_id' => 7, 'training_title' => 'Self-Defense Training',
            'date_start' => '2026-05-02', 'date_end' => '2026-05-04', 'training_hours' => 16,
            'type_of_ld' => 'Technical', 'facilitator' => 'DTRC', 'status' => 'approved'],
        ['training_id' => 101, 'employee_id' => 7, 'training_title' => 'Something She Found Herself',
            'date_start' => '2026-06-02', 'date_end' => '2026-06-04', 'training_hours' => 8,
            'type_of_ld' => 'Technical', 'facilitator' => 'CSC', 'status' => 'approved'],
    ]);
    DB::table('legacy_ldi_training')->insert([
        'ldi_id' => 1, 'training_id' => 99, 'training_title' => 'Self-Defense Training',
        'development_partner' => 'DOH', 'budget' => 54000,
    ]);

    $this->artisan('ldi:import-training-history')->assertSuccessful();

    $plan = LdiTraining::first();

    expect($plan->trainingRecords()->count())->toBe(1)
        ->and(TrainingRecord::where('title', 'Something She Found Herself')->value('ldi_training_id'))->toBeNull();
});

test('a type of LD outside the four PDS ones becomes Other with its own words', function () {
    Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Cruz']);
    User::factory()->hr()->create();

    DB::table('legacy_employees')->insert(['employee_id' => 7, 'firstname' => 'Maria', 'lastname' => 'Cruz']);
    DB::table('legacy_trainings')->insert([
        'training_id' => 1, 'employee_id' => 7, 'training_title' => 'Annual Convention',
        'date_start' => '2025-03-02', 'date_end' => '2025-03-04', 'training_hours' => 8,
        'type_of_ld' => 'Soft Skill', 'facilitator' => 'PHA', 'status' => 'approved',
    ]);

    $this->artisan('ldi:import-training-history')->assertSuccessful();

    $record = TrainingRecord::first();

    expect($record->ld_type)->toBe(LdType::Other)
        ->and($record->ld_type_other)->toBe('Soft Skill')
        ->and($record->ld_type_label)->toBe('Soft Skill');
});

test('a record with no type of LD is reported so somebody can set one', function () {
    Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Cruz']);
    User::factory()->hr()->create();

    DB::table('legacy_employees')->insert(['employee_id' => 7, 'firstname' => 'Maria', 'lastname' => 'Cruz']);
    DB::table('legacy_trainings')->insert([
        'training_id' => 1, 'employee_id' => 7, 'training_title' => 'Unlabelled Training',
        'date_start' => '2025-03-02', 'date_end' => '2025-03-04', 'training_hours' => 8,
        'type_of_ld' => null, 'facilitator' => 'PHA', 'status' => 'approved',
    ]);

    $this->artisan('ldi:import-training-history')
        ->expectsOutputToContain('need one set by hand')
        ->assertSuccessful();
});

test('a still pending record is routed to whoever should approve it now', function () {
    $section = Section::factory()->create();
    $head = Employee::factory()->for($section)->create();
    $section->update(['section_head_employee_id' => $head->id]);

    Employee::factory()->for($section)->create(['first_name' => 'Maria', 'last_name' => 'Cruz']);
    User::factory()->hr()->create();

    DB::table('legacy_employees')->insert(['employee_id' => 7, 'firstname' => 'Maria', 'lastname' => 'Cruz']);
    DB::table('legacy_trainings')->insert([
        'training_id' => 1, 'employee_id' => 7, 'training_title' => 'Still Pending Seminar',
        'date_start' => '2026-03-02', 'date_end' => '2026-03-04', 'training_hours' => 8,
        'type_of_ld' => 'Technical', 'facilitator' => 'CSC', 'status' => 'pending',
    ]);

    $this->artisan('ldi:import-training-history')->assertSuccessful();

    $record = TrainingRecord::first();

    expect($record->status)->toBe(TrainingStatus::Pending)
        ->and($record->current_level)->toBe(ApprovalLevel::SectionHead);
});

test('an employee-less row carrying planning data becomes an LDI training', function () {
    User::factory()->hr()->create();
    Employee::factory()->create();

    DB::table('legacy_trainings')->insert([
        'training_id' => 99, 'employee_id' => null, 'training_title' => 'Self-Defense Training',
        'date_start' => '2026-05-02', 'date_end' => '2026-05-04', 'training_hours' => 16,
        'type_of_ld' => 'Technical', 'facilitator' => 'DTRC Caraga', 'location' => 'Butuan',
        'cpd_units' => 2, 'status' => 'approved',
    ]);
    DB::table('legacy_ldi_training')->insert([
        'ldi_id' => 1, 'training_id' => 99, 'training_title' => 'Self-Defense Training',
        'development_partner' => 'Department of Health', 'type_of_training' => 'Training',
        'target_attendees' => '18', 'budget' => 54000, 'budget_source' => 'WFP-GAA 2026',
    ]);

    $this->artisan('ldi:import-training-history')->assertSuccessful();

    $plan = LdiTraining::first();

    expect(LdiTraining::count())->toBe(1)
        ->and($plan->development_partner)->toBe('Department of Health')
        ->and($plan->facilitator)->toBe('DTRC Caraga')
        ->and($plan->target_attendees)->toBe(18)
        ->and((float) $plan->budget)->toBe(54000.0)
        ->and($plan->budget_source)->toBe('WFP-GAA 2026')
        ->and(TrainingRecord::count())->toBe(0);
});

test('an employee with no local match is reported and skipped', function () {
    User::factory()->hr()->create();

    DB::table('legacy_employees')->insert(['employee_id' => 7, 'firstname' => 'Ghost', 'lastname' => 'Employee']);
    Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Cruz']);

    DB::table('legacy_trainings')->insert([
        'training_id' => 1, 'employee_id' => 7, 'training_title' => 'Orphan Seminar',
        'date_start' => '2025-03-02', 'date_end' => '2025-03-04', 'training_hours' => 8,
        'type_of_ld' => 'Technical', 'facilitator' => 'CSC', 'status' => 'approved',
    ]);

    $this->artisan('ldi:import-training-history')
        ->expectsOutputToContain('no local match')
        ->assertSuccessful();

    expect(TrainingRecord::count())->toBe(0);
});

test('running it twice does not duplicate anything', function () {
    Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Cruz']);
    User::factory()->hr()->create();

    DB::table('legacy_employees')->insert(['employee_id' => 7, 'firstname' => 'Maria', 'lastname' => 'Cruz']);
    DB::table('legacy_trainings')->insert([
        'training_id' => 1, 'employee_id' => 7, 'training_title' => 'Records Management Seminar',
        'date_start' => '2025-03-02', 'date_end' => '2025-03-04', 'training_hours' => 24,
        'type_of_ld' => 'Technical', 'facilitator' => 'CSC', 'status' => 'approved',
    ]);

    $this->artisan('ldi:import-training-history')->assertSuccessful();
    $this->artisan('ldi:import-training-history')->assertSuccessful();

    expect(TrainingRecord::count())->toBe(1);
});

test('it refuses to run when no employee has been imported', function () {
    User::factory()->hr()->create();

    DB::table('legacy_employees')->insert(['employee_id' => 7, 'firstname' => 'Maria', 'lastname' => 'Cruz']);

    $this->artisan('ldi:import-training-history')->assertFailed();
});

test('the legacy total does not land in Other expenses a second time', function () {
    Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Cruz']);
    User::factory()->hr()->create();

    DB::table('legacy_employees')->insert(['employee_id' => 7, 'firstname' => 'Maria', 'lastname' => 'Cruz']);
    DB::table('legacy_trainings')->insert([
        'training_id' => 1, 'employee_id' => 7, 'training_title' => 'Records Management Seminar',
        'date_start' => '2025-03-02', 'date_end' => '2025-03-04', 'training_hours' => 24,
        'type_of_ld' => 'Technical', 'facilitator' => 'Civil Service Commission',
        // Its `expenses` is the whole cost, which is the two beside it.
        'registration_fee' => 1500, 'tev' => 2000, 'expenses' => 3500, 'status' => 'approved',
    ]);

    $this->artisan('ldi:import-training-history')->assertSuccessful();

    $record = TrainingRecord::first();

    expect($record->expenses)->toBeNull()
        ->and((float) $record->registration_fee + (float) $record->tev + (float) $record->expenses)
        ->toBe(3500.0);
});

test('a real third expense survives the import', function () {
    Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Cruz']);
    User::factory()->hr()->create();

    DB::table('legacy_employees')->insert(['employee_id' => 7, 'firstname' => 'Maria', 'lastname' => 'Cruz']);
    DB::table('legacy_trainings')->insert([
        'training_id' => 1, 'employee_id' => 7, 'training_title' => 'Records Management Seminar',
        'date_start' => '2025-03-02', 'date_end' => '2025-03-04', 'training_hours' => 24,
        'type_of_ld' => 'Technical', 'facilitator' => 'Civil Service Commission',
        'registration_fee' => 1500, 'tev' => 2000, 'expenses' => 4200, 'status' => 'approved',
    ]);

    $this->artisan('ldi:import-training-history')->assertSuccessful();

    expect((float) TrainingRecord::first()->expenses)->toBe(700.0);
});
