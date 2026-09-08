<?php

use App\Actions\Reports\DohLdiReport;
use App\Enums\TrainingStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\LdiTraining;
use App\Models\Position;
use App\Models\Section;
use App\Models\TrainingRecord;
use App\Models\User;

/**
 * Puts one attendee on a plan, in a given division and position.
 */
function attendee(LdiTraining $plan, string $divisionCode, ?string $positionTitle, string $gender): Employee
{
    $division = Division::query()->firstWhere('code', $divisionCode)
        ?? Division::factory()->create(['code' => $divisionCode]);

    $employee = Employee::factory()
        ->for(Section::factory()->for($division))
        ->create([
            'gender' => $gender,
            'position_id' => $positionTitle === null
                ? null
                : Position::factory()->create(['title' => $positionTitle])->id,
        ]);

    TrainingRecord::factory()->for($employee)->create([
        'ldi_training_id' => $plan->id,
        'status' => TrainingStatus::Approved,
    ]);

    return $employee;
}

beforeEach(function () {
    config()->set('ldi.doh.mcc_positions', ['MEDICAL SPECIALIST II']);
    config()->set('ldi.doh.dm_division_codes', ['RITD']);
    config()->set('ldi.doh.ad_staff_division_codes', ['AD', 'FA']);
});

test('it counts attendees in the three TRC categories', function () {
    $plan = LdiTraining::factory()->create(['date_start' => '2026-03-02', 'date_end' => '2026-03-04']);

    attendee($plan, 'AD', 'ADMINISTRATIVE OFFICER V', 'Female');
    attendee($plan, 'FA', 'ACCOUNTANT II', 'Male');
    attendee($plan, 'RITD', 'NURSE II', 'Female');
    attendee($plan, 'OCH', 'MEDICAL SPECIALIST II', 'Male');

    $row = app(DohLdiReport::class)->handle(2026, 3)[0];

    expect($row['ad_staff'])->toBe(2)
        ->and($row['dm'])->toBe(1)
        ->and($row['mcc'])->toBe(1)
        ->and($row['female'])->toBe(2)
        ->and($row['male'])->toBe(2);
});

test('a doctor is counted once, as MCC, not also in their division', function () {
    $plan = LdiTraining::factory()->create(['date_start' => '2026-03-02', 'date_end' => '2026-03-04']);

    attendee($plan, 'RITD', 'MEDICAL SPECIALIST II', 'Male');

    $row = app(DohLdiReport::class)->handle(2026, 3)[0];

    expect($row['mcc'])->toBe(1)
        ->and($row['dm'])->toBe(0)
        ->and($row['uncategorised'])->toBe(0);
});

test('an attendee outside the three categories is reported as uncategorised', function () {
    $plan = LdiTraining::factory()->create(['date_start' => '2026-03-02', 'date_end' => '2026-03-04']);

    attendee($plan, 'OAD', 'SOCIAL WELFARE OFFICER II', 'Female');

    $row = app(DohLdiReport::class)->handle(2026, 3)[0];

    expect($row['uncategorised'])->toBe(1)
        ->and($row['mcc'] + $row['dm'] + $row['ad_staff'])->toBe(0)
        ->and($row['female'])->toBe(1);
});

test('a pending attendance is not counted', function () {
    $plan = LdiTraining::factory()->create(['date_start' => '2026-03-02', 'date_end' => '2026-03-04']);

    $employee = Employee::factory()->create(['gender' => 'Female']);
    TrainingRecord::factory()->for($employee)->create([
        'ldi_training_id' => $plan->id,
        'status' => TrainingStatus::Pending,
    ]);

    $row = app(DohLdiReport::class)->handle(2026, 3)[0];

    expect($row['female'])->toBe(0);
});

test('the number of days counts both ends', function () {
    LdiTraining::factory()->create(['date_start' => '2026-03-02', 'date_end' => '2026-03-04']);

    expect(app(DohLdiReport::class)->handle(2026, 3)[0]['days'])->toBe(3);

    LdiTraining::query()->delete();
    LdiTraining::factory()->create(['date_start' => '2026-03-02', 'date_end' => '2026-03-02']);

    expect(app(DohLdiReport::class)->handle(2026, 3)[0]['days'])->toBe(1);
});

test('leaving the month out covers the whole year', function () {
    LdiTraining::factory()->create(['title' => 'March', 'date_start' => '2026-03-02', 'date_end' => '2026-03-04']);
    LdiTraining::factory()->create(['title' => 'July', 'date_start' => '2026-07-02', 'date_end' => '2026-07-04']);
    LdiTraining::factory()->create(['title' => 'Last Year', 'date_start' => '2025-03-02', 'date_end' => '2025-03-04']);

    expect(app(DohLdiReport::class)->handle(2026))->toHaveCount(2)
        ->and(app(DohLdiReport::class)->handle(2026, 3))->toHaveCount(1);
});

test('hr can open the printable form', function () {
    $this->actingAs(User::factory()->hr()->create());

    $plan = LdiTraining::factory()->create([
        'title' => 'Self-Defense and Restraint Training',
        'development_partner' => 'Department of Health',
        'date_start' => '2026-03-02',
        'date_end' => '2026-03-04',
    ]);

    attendee($plan, 'AD', 'ADMINISTRATIVE OFFICER V', 'Female');

    $this->get(route('reports.doh-ldi', ['year' => 2026, 'month' => 3]))
        ->assertOk()
        ->assertSee('Training Report on Attendance to Learning and Development Interventions')
        ->assertSee('Self-Defense and Restraint Training')
        ->assertSee('March 2-4, 2026')
        ->assertSee('DEPARTMENT OF HEALTH');
});

test('a division head cannot open the doh form', function () {
    $this->actingAs(User::factory()->divisionHead()->create());

    $this->get(route('reports.doh-ldi'))->assertForbidden();
});
