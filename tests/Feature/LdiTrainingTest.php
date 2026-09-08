<?php

use App\Actions\Training\AddLdiAttendees;
use App\Enums\TrainingStatus;
use App\Models\Employee;
use App\Models\LdiTraining;
use App\Models\TrainingRecord;
use App\Models\User;

test('adding attendees records approved attendance for each of them', function () {
    $hr = User::factory()->hr()->create();
    $plan = LdiTraining::factory()->create([
        'title' => 'Self-Defense and Restraint Training',
        'development_partner' => 'Department of Health',
        'facilitator' => 'Drug Treatment and Rehabilitation Center Caraga',
    ]);

    $employees = Employee::factory()->count(3)->create();

    $added = app(AddLdiAttendees::class)->handle($plan, $employees->pluck('id')->all(), $hr);

    expect($added)->toBe(3)
        ->and($plan->trainingRecords()->count())->toBe(3);

    $record = $plan->trainingRecords()->first();

    expect($record->status)->toBe(TrainingStatus::Approved)
        ->and($record->current_level)->toBeNull()
        ->and($record->title)->toBe('Self-Defense and Restraint Training')
        ->and($record->submitted_by)->toBe($hr->id);
});

test('attendance is credited to the facilitator, not to whoever paid', function () {
    $hr = User::factory()->hr()->create();
    $plan = LdiTraining::factory()->create([
        'development_partner' => 'Department of Health',
        'facilitator' => 'Drug Treatment and Rehabilitation Center Caraga',
    ]);

    app(AddLdiAttendees::class)->handle($plan, [Employee::factory()->create()->id], $hr);

    // This is what a PDS prints under "Conducted/Sponsored by".
    expect($plan->trainingRecords()->first()->conducted_by)
        ->toBe('Drug Treatment and Rehabilitation Center Caraga');
});

test('the plan cpd units carry over to every attendee', function () {
    $hr = User::factory()->hr()->create();
    $plan = LdiTraining::factory()->create(['cpd_units' => 12.5]);

    app(AddLdiAttendees::class)->handle($plan, [Employee::factory()->create()->id], $hr);

    expect($plan->trainingRecords()->first()->cpd_units)->toBe(12.5);
});

test('somebody already on the list is not added twice', function () {
    $hr = User::factory()->hr()->create();
    $plan = LdiTraining::factory()->create();
    $employee = Employee::factory()->create();

    app(AddLdiAttendees::class)->handle($plan, [$employee->id], $hr);
    $added = app(AddLdiAttendees::class)->handle($plan, [$employee->id], $hr);

    expect($added)->toBe(0)
        ->and($plan->trainingRecords()->count())->toBe(1);
});

test('attendance from a plan skips the approval chain entirely', function () {
    $hr = User::factory()->hr()->create();
    $plan = LdiTraining::factory()->create();
    $employee = Employee::factory()->create();

    app(AddLdiAttendees::class)->handle($plan, [$employee->id], $hr);

    $record = $plan->trainingRecords()->first();

    expect($record->approvals()->count())->toBe(0)
        ->and($record->status)->toBe(TrainingStatus::Approved);
});

test('an employee sees plan attendance as an ordinary training of theirs', function () {
    $hr = User::factory()->hr()->create();
    $plan = LdiTraining::factory()->create(['title' => 'Records Management Seminar']);
    $employee = Employee::factory()->create();

    app(AddLdiAttendees::class)->handle($plan, [$employee->id], $hr);

    expect($employee->trainingRecords()->count())->toBe(1)
        ->and($employee->trainingRecords()->first()->title)->toBe('Records Management Seminar');
});

test('actual spend adds up what the attendees cost', function () {
    $hr = User::factory()->hr()->create();
    $plan = LdiTraining::factory()->create(['budget' => 54000]);
    $employees = Employee::factory()->count(2)->create();

    app(AddLdiAttendees::class)->handle($plan, $employees->pluck('id')->all(), $hr);

    $records = $plan->trainingRecords()->get();
    $records[0]->update(['registration_fee' => 1500, 'tev' => 2000, 'expenses' => 500]);
    $records[1]->update(['registration_fee' => 1500, 'tev' => null, 'expenses' => null]);

    expect($plan->actual_spend)->toBe(5500.0);
});

test('a plan with no attendees has spent nothing', function () {
    expect(LdiTraining::factory()->create()->actual_spend)->toBe(0.0);
});

test('deleting a plan keeps the attendance history', function () {
    $hr = User::factory()->hr()->create();
    $plan = LdiTraining::factory()->create();
    $employee = Employee::factory()->create();

    app(AddLdiAttendees::class)->handle($plan, [$employee->id], $hr);

    $plan->delete();

    expect(TrainingRecord::count())->toBe(1)
        ->and(TrainingRecord::first()->ldi_training_id)->toBeNull();
});

test('only hr and admin may manage plans', function () {
    $plan = LdiTraining::factory()->create();

    expect(User::factory()->hr()->create()->can('update', $plan))->toBeTrue()
        ->and(User::factory()->admin()->create()->can('update', $plan))->toBeTrue()
        ->and(User::factory()->divisionHead()->create()->can('update', $plan))->toBeFalse()
        ->and(User::factory()->employee()->create()->can('viewAny', LdiTraining::class))->toBeFalse();
});
