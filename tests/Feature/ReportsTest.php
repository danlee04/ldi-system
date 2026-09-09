<?php

use App\Actions\Reports\ApprovalsAgingReport;
use App\Actions\Reports\CostPerParticipantReport;
use App\Actions\Reports\CoverageByDivisionReport;
use App\Actions\Reports\EmployeesWithoutTrainingReport;
use App\Actions\Reports\FundUtilizationByDivisionReport;
use App\Actions\Reports\LdiAccomplishmentReport;
use App\Actions\Reports\MonthlyActivityReport;
use App\Actions\Reports\RepeatAttendanceReport;
use App\Enums\ApprovalLevel;
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

test('the gap list says when each of them last attended anything', function () {
    $this->actingAs(User::factory()->hr()->create());

    $lapsed = Employee::factory()->create(['last_name' => 'Lapsed']);
    TrainingRecord::factory()->for($lapsed)->approved()->create(['date_end' => '2024-08-09']);

    Employee::factory()->create(['last_name' => 'Never']);

    $rows = app(EmployeesWithoutTrainingReport::class)->handle(2026);
    $keyed = collect($rows)->keyBy(fn (array $row): string => $row['employee']->last_name);

    expect($keyed['Lapsed']['last_training']->toDateString())->toBe('2024-08-09')
        ->and($keyed['Never']['last_training'])->toBeNull()
        ->and(app(EmployeesWithoutTrainingReport::class)->summarise($rows)['never'])->toBe(1);
});

test('the gap list can be narrowed to one division', function () {
    $this->actingAs(User::factory()->hr()->create());

    $wanted = Division::factory()->create(['code' => 'FAD']);
    $other = Division::factory()->create(['code' => 'RITD']);

    Employee::factory()->for(Section::factory()->for($wanted))->create(['last_name' => 'Inside']);
    Employee::factory()->for(Section::factory()->for($other))->create(['last_name' => 'Outside']);

    $rows = app(EmployeesWithoutTrainingReport::class)->handle(2026, $wanted->id);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['employee']->last_name)->toBe('Inside');
});

test('coverage counts how much of each division the year reached', function () {
    $this->actingAs(User::factory()->hr()->create());

    $division = Division::factory()->create(['code' => 'FAD']);
    $section = Section::factory()->for($division)->create();

    $reached = Employee::factory()->for($section)->create();
    Employee::factory()->for($section)->create();

    TrainingRecord::factory()->for($reached)->approved()->create(['date_end' => '2026-05-04']);

    $rows = app(CoverageByDivisionReport::class)->handle(2026);
    $row = collect($rows)->firstWhere('division', 'FAD');

    expect($row)->toMatchArray(['employees' => 2, 'covered' => 1, 'percentage' => 50.0])
        ->and(app(CoverageByDivisionReport::class)->summarise($rows)['percentage'])->toBe(50.0);
});

test('a division with nobody in it is left out of coverage', function () {
    $this->actingAs(User::factory()->hr()->create());

    Division::factory()->create(['code' => 'EMPTY']);

    expect(app(CoverageByDivisionReport::class)->handle(2026))->toBeEmpty();
});

test('somebody with training in an earlier year counts as a repeat', function () {
    $this->actingAs(User::factory()->hr()->create());

    $repeat = Employee::factory()->create(['last_name' => 'Repeat']);
    TrainingRecord::factory()->for($repeat)->approved()->create(['date_end' => '2025-03-04']);
    TrainingRecord::factory()->for($repeat)->approved()->create(['date_end' => '2026-03-04']);

    $newcomer = Employee::factory()->create(['last_name' => 'Newcomer']);
    TrainingRecord::factory()->for($newcomer)->approved()->create(['date_end' => '2026-04-04']);

    // Nothing this year, so out of the report altogether.
    Employee::factory()->create(['last_name' => 'Absent']);

    $rows = app(RepeatAttendanceReport::class)->handle(2026);
    $keyed = collect($rows)->keyBy(fn (array $row): string => $row['employee']->last_name);

    expect($rows)->toHaveCount(2)
        ->and($keyed['Repeat']['first_timer'])->toBeFalse()
        ->and($keyed['Repeat']['earlier'])->toBe(1)
        ->and($keyed['Newcomer']['first_timer'])->toBeTrue()
        ->and(app(RepeatAttendanceReport::class)->summarise($rows))
        ->toMatchArray(['attendees' => 2, 'first_timers' => 1, 'repeats' => 1]);
});

test('the accomplishment report counts who really turned up', function () {
    $this->actingAs(User::factory()->hr()->create());

    $plan = LdiTraining::factory()->create([
        'title' => 'Records Management Seminar',
        'date_start' => '2026-02-10',
        'date_end' => '2026-02-12',
        'target_attendees' => 20,
    ]);

    TrainingRecord::factory()->for($plan, 'ldiTraining')->approved()->create([
        'registration_fee' => 1500,
        'tev' => 500,
        'expenses' => 0,
    ]);

    TrainingRecord::factory()->for($plan, 'ldiTraining')->create(['status' => TrainingStatus::Pending]);

    $rows = app(LdiAccomplishmentReport::class)->handle(2026);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['attendees'])->toBe(1)
        ->and($rows[0]['spent'])->toBe(2000.0)
        ->and($rows[0]['plan']->target_attendees)->toBe(20);
});

test('the accomplishment report can be read one quarter at a time', function () {
    $this->actingAs(User::factory()->hr()->create());

    LdiTraining::factory()->create(['title' => 'First quarter', 'date_start' => '2026-02-10', 'date_end' => '2026-02-11']);
    LdiTraining::factory()->create(['title' => 'Third quarter', 'date_start' => '2026-08-10', 'date_end' => '2026-08-11']);

    $rows = app(LdiAccomplishmentReport::class)->handle(2026, 1);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['plan']->title)->toBe('First quarter');
});

test('fund utilization splits each division across the quarters', function () {
    $this->actingAs(User::factory()->hr()->create());

    $division = Division::factory()->create(['code' => 'FAD']);
    $employee = Employee::factory()->for(Section::factory()->for($division))->create();

    TrainingRecord::factory()->for($employee)->approved()->create([
        'date_end' => '2026-02-04',
        'registration_fee' => 1000,
        'tev' => 500,
        'expenses' => 0,
    ]);

    TrainingRecord::factory()->for($employee)->approved()->create([
        'date_end' => '2026-11-04',
        'registration_fee' => 2000,
        'tev' => 0,
        'expenses' => 250,
    ]);

    $row = collect(app(FundUtilizationByDivisionReport::class)->handle(2026))->firstWhere('division', 'FAD');

    expect($row['quarters'][1])->toBe(1500.0)
        ->and($row['quarters'][2])->toBe(0.0)
        ->and($row['quarters'][4])->toBe(2250.0)
        ->and($row['total'])->toBe(3750.0)
        ->and($row['attendances'])->toBe(2);
});

test('cost per participant groups by whoever conducted the training', function () {
    $this->actingAs(User::factory()->hr()->create());

    $employee = Employee::factory()->create();

    TrainingRecord::factory()->for($employee)->approved()->create([
        'conducted_by' => 'Civil Service Commission',
        'date_end' => '2026-03-04',
        'registration_fee' => 1000,
        'tev' => 0,
        'expenses' => 0,
    ]);

    TrainingRecord::factory()->for(Employee::factory())->approved()->create([
        'conducted_by' => 'Civil Service Commission',
        'date_end' => '2026-04-04',
        'registration_fee' => 3000,
        'tev' => 0,
        'expenses' => 0,
    ]);

    $row = collect(app(CostPerParticipantReport::class)->handle(2026))
        ->firstWhere('provider', 'Civil Service Commission');

    expect($row['attendances'])->toBe(2)
        ->and($row['employees'])->toBe(2)
        ->and($row['cost'])->toBe(4000.0)
        ->and($row['per_participant'])->toBe(2000.0);
});

test('approvals aging puts the longest wait first and names what has no approver', function () {
    $this->actingAs(User::factory()->hr()->create());

    $old = TrainingRecord::factory()->create([
        'title' => 'Waiting longest',
        'status' => TrainingStatus::Pending,
        'current_level' => ApprovalLevel::DivisionHead,
    ]);
    $old->forceFill(['created_at' => now()->subDays(30)])->save();

    $recent = TrainingRecord::factory()->create([
        'title' => 'Waiting briefly',
        'status' => TrainingStatus::Pending,
        'current_level' => null,
    ]);
    $recent->forceFill(['created_at' => now()->subDays(2)])->save();

    TrainingRecord::factory()->approved()->create(['title' => 'Already decided']);

    $records = app(ApprovalsAgingReport::class)->handle();
    $totals = app(ApprovalsAgingReport::class)->summarise($records);

    expect($records)->toHaveCount(2)
        ->and($records->first()->title)->toBe('Waiting longest')
        ->and(app(ApprovalsAgingReport::class)->daysWaiting($records->first()))->toBe(30)
        ->and($totals)->toMatchArray(['pending' => 2, 'unroutable' => 1, 'longest' => 30])
        ->and(app(ApprovalsAgingReport::class)->waitingOn($recent))->toBe('Nobody — no head designated');
});

test('every report on the page has a csv behind it', function () {
    $this->actingAs(User::factory()->hr()->create());

    $names = [
        'without' => 'employees-without-training-2026.csv',
        'coverage' => 'coverage-by-division-2026.csv',
        'repeat' => 'repeat-and-first-timers-2026.csv',
        'accomplishment' => 'ldi-accomplishment-2026.csv',
        'budget' => 'budget-utilization-2026.csv',
        'funds' => 'fund-utilization-by-division-2026.csv',
        'providers' => 'cost-per-participant-2026.csv',
        'activity' => 'training-activity-2026-3.csv',
    ];

    foreach ($names as $report => $file) {
        Livewire::test('pages::reports')
            ->set('report', $report)
            ->set('year', 2026)
            ->set('month', 3)
            ->call('download')
            ->assertFileDownloaded($file);
    }
});

test('choosing another division drops a section that no longer belongs to it', function () {
    $this->actingAs(User::factory()->hr()->create());

    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();

    Livewire::test('pages::reports')
        ->set('report', 'without')
        ->set('sectionId', (string) $section->id)
        ->set('divisionId', (string) Division::factory()->create()->id)
        ->assertSet('sectionId', '');
});

test('an employee cannot open the reports page', function () {
    $this->actingAs(User::factory()->employee()->create());

    $this->get(route('reports'))->assertForbidden();
});

test('every report on the page renders', function () {
    $this->actingAs(User::factory()->hr()->create());

    $employee = Employee::factory()->for(Section::factory()->for(Division::factory()))->create();
    TrainingRecord::factory()->for($employee)->approved()->create(['date_end' => '2026-03-04']);
    TrainingRecord::factory()->create(['status' => TrainingStatus::Pending, 'current_level' => null]);
    LdiTraining::factory()->create(['date_start' => '2026-03-02', 'date_end' => '2026-03-04']);

    foreach (array_keys(Livewire::test('pages::reports')->instance()::REPORTS) as $report) {
        Livewire::test('pages::reports')
            ->set('report', $report)
            ->set('year', 2026)
            ->set('month', 3)
            ->assertOk();
    }
});
