<?php

use App\Actions\Reports\MonthlyActivityReport;
use App\Enums\TrainingStatus;
use App\Models\BudgetCap;
use App\Models\Division;
use App\Models\Employee;
use App\Models\LdiTraining;
use App\Models\Section;
use App\Models\TrainingRecord;
use App\Models\User;
use Livewire\Livewire;

test('only hr and admin can open reports', function () {
    $this->actingAs(User::factory()->divisionHead()->create());

    $this->get(route('reports'))->assertForbidden();
});

test('monthly activity counts only approved training that ended in the month', function () {
    $this->actingAs(User::factory()->hr()->create());

    $employee = Employee::factory()->create();

    TrainingRecord::factory()->for($employee)->approved()->create([
        'title' => 'In March',
        'date_start' => '2026-03-02',
        'date_end' => '2026-03-04',
        'hours' => 24,
        'registration_fee' => 1500,
        'tev' => 500,
        'expenses' => null,
    ]);
    TrainingRecord::factory()->for($employee)->approved()->create([
        'title' => 'In April',
        'date_start' => '2026-04-02',
        'date_end' => '2026-04-04',
    ]);
    TrainingRecord::factory()->for($employee)->create([
        'title' => 'Still Pending',
        'date_start' => '2026-03-02',
        'date_end' => '2026-03-04',
    ]);

    $component = Livewire::test('pages::reports')
        ->set('report', 'activity')
        ->set('year', 2026)
        ->set('month', 3);

    expect($component->instance()->activityTotals)->toMatchArray([
        'attendances' => 1,
        'employees' => 1,
        'hours' => 24,
        'cost' => 2000.0,
    ]);

    $component->assertSee('In March')->assertDontSee('In April')->assertDontSee('Still Pending');
});

test('a training that started last month still counts in the month it ended', function () {
    $this->actingAs(User::factory()->hr()->create());

    TrainingRecord::factory()->approved()->create([
        'title' => 'Straddles The Turn',
        'date_start' => '2026-02-26',
        'date_end' => '2026-03-02',
    ]);

    Livewire::test('pages::reports')
        ->set('report', 'activity')
        ->set('year', 2026)
        ->set('month', 3)
        ->assertSee('Straddles The Turn');
});

test('employees without training lists whoever finished nothing that year', function () {
    $this->actingAs(User::factory()->hr()->create());

    $trained = Employee::factory()->create(['last_name' => 'Trained']);
    TrainingRecord::factory()->for($trained)->approved()->create(['date_end' => '2026-05-04']);

    Employee::factory()->create(['last_name' => 'Untrained']);

    $component = Livewire::test('pages::reports')
        ->set('report', 'without')
        ->set('year', 2026);

    expect($component->instance()->withoutTotals)->toMatchArray(['without' => 1, 'active' => 2]);

    $component->assertSee('Untrained')->assertDontSee(', Trained');
});

test('a pending record does not count as having trained', function () {
    $this->actingAs(User::factory()->hr()->create());

    $employee = Employee::factory()->create(['last_name' => 'Waiting']);
    TrainingRecord::factory()->for($employee)->create([
        'status' => TrainingStatus::Pending,
        'date_end' => '2026-05-04',
    ]);

    $component = Livewire::test('pages::reports')
        ->set('report', 'without')
        ->set('year', 2026);

    expect($component->instance()->withoutTotals['without'])->toBe(1);
});

test('an inactive employee is left out of the gap list', function () {
    $this->actingAs(User::factory()->hr()->create());

    Employee::factory()->inactive()->create(['last_name' => 'Retired']);

    $component = Livewire::test('pages::reports')
        ->set('report', 'without')
        ->set('year', 2026);

    expect($component->instance()->withoutTotals)->toMatchArray(['without' => 0, 'active' => 0]);
});

test('budget utilization separates what was committed from what was spent', function () {
    $this->actingAs(User::factory()->hr()->create());

    BudgetCap::factory()->create([
        'year' => 2026,
        'budget_source' => 'Human Resource',
        'amount' => 300000,
    ]);

    $plan = LdiTraining::factory()->create([
        'budget_source' => 'Human Resource',
        'budget' => 54000,
        'date_start' => '2026-03-02',
        'date_end' => '2026-03-04',
    ]);

    TrainingRecord::factory()->approved()->create([
        'ldi_training_id' => $plan->id,
        'registration_fee' => 1500,
        'tev' => 2000,
        'expenses' => null,
    ]);

    $row = Livewire::test('pages::reports')
        ->set('report', 'budget')
        ->set('year', 2026)
        ->instance()->budget[0];

    expect($row['source'])->toBe('Human Resource')
        ->and($row['cap'])->toBe(300000.0)
        ->and($row['committed'])->toBe(54000.0)
        ->and($row['spent'])->toBe(3500.0)
        ->and($row['plans'])->toBe(1);
});

test('a source with plans but no cap is still reported', function () {
    $this->actingAs(User::factory()->hr()->create());

    LdiTraining::factory()->create([
        'budget_source' => 'WFP-GAA 2026',
        'budget' => 20000,
        'date_start' => '2026-03-02',
        'date_end' => '2026-03-04',
    ]);

    $row = Livewire::test('pages::reports')
        ->set('report', 'budget')
        ->set('year', 2026)
        ->instance()->budget[0];

    expect($row['source'])->toBe('WFP-GAA 2026')
        ->and($row['cap'])->toBeNull()
        ->and($row['committed'])->toBe(20000.0);
});

test('the csv carries the rows of whichever report is open', function () {
    $this->actingAs(User::factory()->hr()->create());

    $division = Division::factory()->create(['code' => 'FAD']);
    $employee = Employee::factory()
        ->for(Section::factory()->for($division))
        ->create(['first_name' => 'Maria', 'middle_name' => null, 'last_name' => 'Cruz']);

    TrainingRecord::factory()->for($employee)->approved()->create([
        'title' => 'Records Management Seminar',
        'date_start' => '2026-03-02',
        'date_end' => '2026-03-04',
    ]);

    $records = app(MonthlyActivityReport::class)->handle(2026, 3);
    $rows = app(MonthlyActivityReport::class)->toRows($records);

    expect($rows[0])->toBe(['Division', 'Section', 'Employee', 'Training', 'Inclusive dates', 'Hours', 'Type of LD', 'Conducted by', 'Cost'])
        ->and($rows[1][0])->toBe('FAD')
        ->and($rows[1][2])->toBe('Cruz, Maria')
        ->and($rows[1][3])->toBe('Records Management Seminar')
        ->and($rows[1][4])->toBe('March 2-4, 2026');
});

test('downloading hands back a csv named for the report', function () {
    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::reports')
        ->set('report', 'without')
        ->set('year', 2026)
        ->call('download')
        ->assertFileDownloaded('employees-without-training-2026.csv');
});
