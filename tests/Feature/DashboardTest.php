<?php

use App\Enums\ActivityType;
use App\Enums\ApprovalLevel;
use App\Enums\LdType;
use App\Models\Activity;
use App\Models\Division;
use App\Models\Employee;
use App\Models\EmployeeEligibility;
use App\Models\LdiTraining;
use App\Models\Section;
use App\Models\TrainingRecord;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard counts my own records by state', function () {
    $user = User::factory()->employee()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    TrainingRecord::factory()->for($employee)->count(2)->create();
    TrainingRecord::factory()->for($employee)->approved()->create();
    TrainingRecord::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSet('myPending', 2)
        ->assertSet('myApproved', 1);
});

test('hr is warned about records nobody can approve', function () {
    $section = Section::factory()->create(['section_head_employee_id' => null]);
    $employee = Employee::factory()->for($section)->create();

    TrainingRecord::factory()->for($employee)->create(['current_level' => null]);

    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::dashboard')
        ->assertSet('unroutable', 1)
        ->assertSee('cannot move because no head is designated');
});

test('an ordinary employee is not shown the unroutable warning', function () {
    $section = Section::factory()->create(['section_head_employee_id' => null]);
    $employee = Employee::factory()->for($section)->create();

    TrainingRecord::factory()->for($employee)->create(['current_level' => null]);

    $user = User::factory()->employee()->create();
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')->assertSet('unroutable', 0);
});

test('a submission says who it is sitting with', function () {
    $section = Section::factory()->create();
    $headUser = User::factory()->sectionHead()->create();
    $head = Employee::factory()->for($section)->create([
        'user_id' => $headUser->id,
        'first_name' => 'Juana',
        'middle_name' => null,
        'last_name' => 'Dela Cruz',
    ]);
    $section->update(['section_head_employee_id' => $head->id]);

    $user = User::factory()->employee()->create();
    $employee = Employee::factory()->for($section)->create(['user_id' => $user->id]);

    TrainingRecord::factory()->for($employee)->create([
        'title' => 'Records Management Seminar',
        'current_level' => ApprovalLevel::SectionHead,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('Records Management Seminar')
        ->assertSee('Dela Cruz, Juana');
});

test('a submission nobody can approve says so rather than naming a level', function () {
    $section = Section::factory()->create(['section_head_employee_id' => null]);
    $user = User::factory()->employee()->create();
    $employee = Employee::factory()->for($section)->create(['user_id' => $user->id]);

    TrainingRecord::factory()->for($employee)->create(['current_level' => null]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')->assertSee('no head is designated');
});

test('the cpd tile counts only approved training that ended this year', function () {
    $user = User::factory()->employee()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    TrainingRecord::factory()->for($employee)->approved()->create([
        'cpd_units' => 9,
        'date_end' => now()->startOfYear()->addMonth(),
    ]);

    TrainingRecord::factory()->for($employee)->approved()->create([
        'cpd_units' => 4,
        'date_end' => now()->subYear(),
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::dashboard');

    expect($component->instance()->cpdUnits)->toBe(9.0)
        ->and($component->instance()->approvedThisYear)->toBe(1);
});

test('eligibility about to lapse is raised, and a distant one is not', function () {
    $user = User::factory()->employee()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    EmployeeEligibility::factory()->for($employee)->create([
        'detail' => 'Registered Nurse, PRC',
        'date_of_validity' => today()->addMonths(6),
    ]);

    EmployeeEligibility::factory()->for($employee)->create([
        'detail' => 'Career Service Professional',
        'date_of_validity' => today()->addYears(4),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('Registered Nurse, PRC')
        ->assertDontSee('Career Service Professional');
});

test('an administrative account gets no personal panels', function () {
    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::dashboard')
        ->assertDontSee('My PDS')
        ->assertDontSee('Where my submissions stand')
        ->assertSee('Training completed each month');
});

test('an employee is not shown the agency panels', function () {
    $user = User::factory()->employee()->create();
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSet('seesAgency', false)
        ->assertDontSee('Training completed each month')
        ->assertDontSee('Quick stats');
});

test('hr is shown the agency panels', function () {
    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::dashboard')
        ->assertSee('Training completed each month')
        ->assertSee('Training coverage by division')
        ->assertSee('Quick stats')
        ->assertSee('Eligibility alerts');
});

test('every month of the year is drawn, including the empty ones', function () {
    $this->actingAs(User::factory()->hr()->create());

    TrainingRecord::factory()->approved()->create(['date_end' => now()->startOfYear()->addMonths(3)]);

    $component = Livewire::test('pages::dashboard')->instance();

    expect($component->months)->toHaveCount(12)
        ->and($component->months[3]['attendances'])->toBe(1)
        ->and($component->months[11]['attendances'])->toBe(0)
        // A year with nothing in it must not divide by zero.
        ->and($component->monthPeak)->toBeGreaterThan(0);
});

test('a year with no training still has a peak to draw against', function () {
    $this->actingAs(User::factory()->hr()->create());

    expect(Livewire::test('pages::dashboard')->instance()->monthPeak)->toBe(1);
});

test('the spend panel adds up the three parts of a record', function () {
    $this->actingAs(User::factory()->hr()->create());

    TrainingRecord::factory()->approved()->create([
        'date_end' => now(),
        'registration_fee' => 1000,
        'tev' => 250,
        'expenses' => 500,
    ]);

    // Not approved, so not counted.
    TrainingRecord::factory()->create([
        'date_end' => now(),
        'registration_fee' => 9999,
        'tev' => 0,
        'expenses' => 0,
    ]);

    $spend = Livewire::test('pages::dashboard')->instance()->spend;

    expect($spend['registration'])->toBe(1000.0)
        ->and($spend['tev'])->toBe(250.0)
        ->and($spend['other'])->toBe(500.0)
        ->and($spend['total'])->toBe(1750.0);
});

test('the ld mix leads with the commonest type and carries its share', function () {
    $this->actingAs(User::factory()->hr()->create());

    TrainingRecord::factory()->count(3)->approved()->create([
        'date_end' => now(),
        'ld_type' => LdType::Technical,
    ]);

    TrainingRecord::factory()->approved()->create([
        'date_end' => now(),
        'ld_type' => LdType::Supervisory,
    ]);

    $mix = Livewire::test('pages::dashboard')->instance()->ldMix;

    expect($mix[0]['label'])->toBe('Technical')
        ->and($mix[0]['attendances'])->toBe(3)
        ->and($mix[0]['share'])->toBe(75.0)
        ->and($mix[1]['label'])->toBe('Supervisory');
});

test('the calendar draws a plan as one bar across the days it runs', function () {
    $this->actingAs(User::factory()->hr()->create());

    LdiTraining::factory()->create([
        'title' => 'Records Management Seminar',
        'date_start' => today()->startOfMonth()->addDays(9),
        'date_end' => today()->startOfMonth()->addDays(11),
    ]);

    $calendar = Livewire::test('pages::dashboard')->instance()->calendar;
    $bars = collect($calendar['weeks'])->pluck('bars')->flatten(1);

    expect($bars->sum('span'))->toBe(3)
        ->and($bars->first()['title'])->toBe('Records Management Seminar')
        // The calendar page's purple, so the two agree about a training.
        ->and($bars->first()['classes'])->toBe(ActivityType::PLAN_CHIP);
});

test('the calendar draws an activity in the colour of its kind', function () {
    $this->actingAs(User::factory()->hr()->create());

    $day = today()->startOfMonth()->addDays(4);

    Activity::factory()->create([
        'title' => 'Management Committee Meeting',
        'type' => ActivityType::Meeting,
        'date_start' => $day,
        'date_end' => $day,
    ]);

    $calendar = Livewire::test('pages::dashboard')->instance()->calendar;
    $bars = collect($calendar['weeks'])->pluck('bars')->flatten(1);

    expect($bars)->toHaveCount(1)
        ->and($bars->first()['classes'])->toBe(ActivityType::Meeting->chipClasses())
        // The legend names only what the month actually holds.
        ->and(collect($calendar['legend'])->pluck('label')->all())->toBe(['Meeting']);
});

test('two things on the same day are stacked, not hidden', function () {
    $this->actingAs(User::factory()->hr()->create());

    $day = today()->startOfMonth()->addDays(4);

    Activity::factory()->create(['type' => ActivityType::Meeting, 'date_start' => $day, 'date_end' => $day]);
    LdiTraining::factory()->create(['date_start' => $day, 'date_end' => $day]);

    $calendar = Livewire::test('pages::dashboard')->instance()->calendar;
    $bars = collect($calendar['weeks'])->pluck('bars')->flatten(1);

    expect($bars->pluck('lane')->all())->toBe([0, 1])
        ->and($calendar['legend'])->toHaveCount(2);
});

test('the month list under the dashboard calendar is gone', function () {
    $this->actingAs(User::factory()->hr()->create());

    LdiTraining::factory()->create([
        'title' => 'Consultation on the Proposed DOH Information Systems Strategic Plan',
        'date_start' => today()->startOfMonth()->addDays(9),
        'date_end' => today()->startOfMonth()->addDays(13),
    ]);

    Livewire::test('pages::dashboard')
        // The title is written on the bar itself, not in a list beneath it.
        ->assertSee('Consultation on the Proposed DOH Information Systems Strategic Plan')
        ->assertDontSee('Nothing is on the calendar this month')
        ->assertSee('Open the calendar');
});

test('the calendar can be stepped back and forward', function () {
    $this->actingAs(User::factory()->hr()->create());

    $component = Livewire::test('pages::dashboard');

    expect($component->instance()->calendar['month']->month)->toBe(today()->month);

    $component->call('previousMonth');

    expect($component->instance()->calendar['month']->format('Y-m'))
        ->toBe(today()->startOfMonth()->subMonth()->format('Y-m'));

    $component->call('nextMonth')->call('nextMonth');

    expect($component->instance()->calendar['month']->format('Y-m'))
        ->toBe(today()->startOfMonth()->addMonth()->format('Y-m'));
});

test('the agency eligibility list holds the lapsed and the lapsing, not the distant', function () {
    $this->actingAs(User::factory()->hr()->create());

    EmployeeEligibility::factory()->create(['date_of_validity' => today()->subMonth()]);
    EmployeeEligibility::factory()->create(['date_of_validity' => today()->addMonths(3)]);
    EmployeeEligibility::factory()->create(['date_of_validity' => today()->addYears(3)]);
    EmployeeEligibility::factory()->create(['date_of_validity' => null]);

    $alerts = Livewire::test('pages::dashboard')->instance()->agencyEligibilityAlerts;

    // Soonest first, so the overdue one leads.
    expect($alerts)->toHaveCount(2)
        ->and($alerts->first()->date_of_validity->toDateString())->toBe(today()->subMonth()->toDateString());
});

test('the employee card totals the roster and splits it by division', function () {
    $this->actingAs(User::factory()->hr()->create());

    $wanted = Division::factory()->create(['code' => 'FAD']);
    Employee::factory()->count(2)->for(Section::factory()->for($wanted))->create();
    Employee::factory()->for(Section::factory()->for(Division::factory()->create(['code' => 'RITD'])))->create();

    // Inactive people are not on the roster.
    Employee::factory()->inactive()->for(Section::factory()->for($wanted))->create();

    $totals = Livewire::test('pages::dashboard')->instance()->employeeTotals;

    expect($totals['total'])->toBe(3)
        ->and($totals['rows'][0])->toBe(['label' => 'FAD', 'count' => 2])
        ->and($totals['rows'][1])->toBe(['label' => 'RITD', 'count' => 1]);
});

test('a plan with no communication recorded is counted rather than dropped', function () {
    $this->actingAs(User::factory()->hr()->create());

    LdiTraining::factory()->count(2)->create([
        'date_start' => now(),
        'date_end' => now(),
        'training_communication' => 'Requested Training',
    ]);

    LdiTraining::factory()->create([
        'date_start' => now(),
        'date_end' => now(),
        'training_communication' => null,
    ]);

    $totals = Livewire::test('pages::dashboard')->instance()->planTotals;

    expect($totals['total'])->toBe(3)
        ->and($totals['rows'][0])->toBe(['label' => 'Requested Training', 'count' => 2])
        ->and($totals['rows'][1])->toBe(['label' => 'Not stated', 'count' => 1]);
});

test('the funding card counts only what hr actually put in', function () {
    $this->actingAs(User::factory()->hr()->create());

    // A 19,000 plan of which HR carried the 6,000 registration.
    LdiTraining::factory()->create([
        'date_start' => now(),
        'date_end' => now(),
        'budget' => 19000,
        'budget_source' => 'Human Resource',
        'budget_amount' => 6000,
        'other_budget_source' => 'WFP- Hospital Income',
        'other_budget_amount' => 13000,
    ]);

    $funding = Livewire::test('pages::dashboard')->instance()->fundingTotals;

    expect($funding['hr'])->toBe(6000.0)
        ->and($funding['other'])->toBe(13000.0)
        ->and($funding['total'])->toBe(19000.0);
});

test('one fund with no amount stated carries the whole budget', function () {
    $this->actingAs(User::factory()->hr()->create());

    LdiTraining::factory()->create([
        'date_start' => now(),
        'date_end' => now(),
        'budget' => 19000,
        'budget_source' => 'Human Resource',
        'budget_amount' => null,
        'other_budget_source' => null,
    ]);

    expect(Livewire::test('pages::dashboard')->instance()->fundingTotals['hr'])->toBe(19000.0);
});

test('a fund that put in nothing adds nothing to the card', function () {
    $this->actingAs(User::factory()->hr()->create());

    LdiTraining::factory()->create([
        'date_start' => now(),
        'date_end' => now(),
        'budget' => 0,
        'budget_source' => 'Funded Nothing',
    ]);

    $funding = Livewire::test('pages::dashboard')->instance()->fundingTotals;

    expect($funding['total'])->toBe(0.0)
        ->and($funding['other'])->toBe(0.0);
});

test('the four figures are shown to hr and not to an employee', function () {
    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::dashboard')
        ->assertSee('Employees')
        ->assertSee('Trained in '.now()->year)
        ->assertSee('Spent in '.now()->year);

    $user = User::factory()->employee()->create();
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertDontSee('Trained in '.now()->year)
        ->assertDontSee('Spent in '.now()->year);
});

test('the coverage figure is the whole agency, not one division', function () {
    $this->actingAs(User::factory()->hr()->create());

    $division = Division::factory()->create();
    $employees = Employee::factory()->count(4)->create(['division_id' => $division->id]);

    // Three of the four finished something this year.
    foreach ($employees->take(3) as $employee) {
        TrainingRecord::factory()->approved()->for($employee)->create([
            'date_start' => now()->subDays(10),
            'date_end' => now()->subDays(8),
        ]);
    }

    $coverage = Livewire::test('pages::dashboard')->instance()->coverageTotal;

    expect($coverage['covered'])->toBe(3)
        ->and($coverage['employees'])->toBe(4)
        ->and($coverage['percentage'])->toBe(75);
});

test('the expenses card leads with what was spent, not what was set aside', function () {
    $this->actingAs(User::factory()->hr()->create());

    $plan = LdiTraining::factory()->create([
        'date_start' => now(),
        'date_end' => now(),
        'budget' => 19000,
        'budget_source' => 'Human Resource',
        'budget_amount' => 6000,
        'other_budget_source' => 'WFP- Hospital Income',
        'other_budget_amount' => 13000,
    ]);

    TrainingRecord::factory()->approved()->create([
        'ldi_training_id' => $plan->id,
        'date_start' => now(),
        'date_end' => now(),
        'registration_fee' => 6000,
        'tev' => 4200,
        'expenses' => null,
    ]);

    Livewire::test('pages::dashboard')
        // What was spent leads the card.
        ->assertSee('10,200.00')
        // What HR set aside supports it.
        ->assertSee('6,000.00 funded by HR');
});

test('the monthly chart can be narrowed to one division', function () {
    $this->actingAs(User::factory()->hr()->create());

    // An employee's division follows their section, so the section is
    // what a test has to place them through.
    $mine = Division::factory()->create(['name' => 'Rehabilitation']);
    $theirs = Division::factory()->create(['name' => 'Administrative']);

    foreach ([$mine, $theirs] as $division) {
        $section = Section::factory()->create(['division_id' => $division->id]);

        TrainingRecord::factory()->approved()->for(Employee::factory()->create(['section_id' => $section->id]))->create([
            'date_start' => now()->startOfYear()->addMonths(3),
            'date_end' => now()->startOfYear()->addMonths(3)->addDays(2),
        ]);
    }

    $component = Livewire::test('pages::dashboard');

    expect($component->instance()->months[3]['attendances'])->toBe(2);

    $component->set('chartDivision', $mine->id);

    expect($component->instance()->months[3]['attendances'])->toBe(1);
});

test('picking a month turns the chart on its side', function () {
    $this->actingAs(User::factory()->hr()->create());

    $division = Division::factory()->create(['name' => 'Rehabilitation']);
    $section = Section::factory()->create(['division_id' => $division->id]);

    TrainingRecord::factory()->approved()->for(Employee::factory()->create(['section_id' => $section->id]))->create([
        'date_start' => now()->startOfYear()->addMonths(3),
        'date_end' => now()->startOfYear()->addMonths(3)->addDays(2),
    ]);

    $component = Livewire::test('pages::dashboard')->set('chartMonth', 4);

    // One month is one bar, so the bars become the divisions that were in it.
    $rows = $component->instance()->months;

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['label'])->toBe('Rehabilitation')
        ->and($rows[0]['attendances'])->toBe(1);

    $component->assertSee('Training completed in April');
});

test('a month with nothing in it says so instead of drawing an empty chart', function () {
    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::dashboard')
        ->set('chartMonth', 7)
        ->assertSee('Nothing was completed here.');
});

test('the chart has its own year, and moving it leaves the cards alone', function () {
    $this->actingAs(User::factory()->hr()->create());

    $division = Division::factory()->create();
    $section = Section::factory()->create(['division_id' => $division->id]);
    $employee = Employee::factory()->create(['section_id' => $section->id]);

    TrainingRecord::factory()->approved()->for($employee)->create([
        'date_start' => now()->subYear()->startOfYear()->addMonths(1),
        'date_end' => now()->subYear()->startOfYear()->addMonths(1)->addDays(2),
    ]);

    $component = Livewire::test('pages::dashboard');

    // Nothing this year, so the chart is empty until the year moves.
    expect(collect($component->instance()->months)->sum('attendances'))->toBe(0);

    $component->set('chartYear', now()->year - 1);

    expect(collect($component->instance()->months)->sum('attendances'))->toBe(1)
        // The cards still report on the year that is running.
        ->and($component->instance()->year())->toBe(now()->year)
        ->and($component->instance()->coverageTotal['covered'])->toBe(0);
});

test('the year to pick from includes the running one even with nothing in it', function () {
    $this->actingAs(User::factory()->hr()->create());

    expect(Livewire::test('pages::dashboard')->instance()->chartYears)->toBe([now()->year]);
});

test('the monthly chart counts the plans that ran alongside the attendances', function () {
    $this->actingAs(User::factory()->hr()->create());

    $when = now()->startOfYear()->addMonths(3);

    LdiTraining::factory()->count(2)->create([
        'date_start' => $when,
        'date_end' => $when->copy()->addDays(2),
    ]);

    TrainingRecord::factory()->approved()->create([
        'date_start' => $when,
        'date_end' => $when->copy()->addDays(2),
    ]);

    $april = Livewire::test('pages::dashboard')->instance()->months[3];

    expect($april['attendances'])->toBe(1)
        ->and($april['plans'])->toBe(2);
});

test('a division sees the plans it actually sent somebody to', function () {
    $this->actingAs(User::factory()->hr()->create());

    $when = now()->startOfYear()->addMonths(3);
    $division = Division::factory()->create();
    $section = Section::factory()->create(['division_id' => $division->id]);
    $employee = Employee::factory()->create(['section_id' => $section->id]);

    $attended = LdiTraining::factory()->create(['date_start' => $when, 'date_end' => $when->copy()->addDays(2)]);
    LdiTraining::factory()->create(['date_start' => $when, 'date_end' => $when->copy()->addDays(2)]);

    TrainingRecord::factory()->approved()->for($employee)->create([
        'ldi_training_id' => $attended->id,
        'date_start' => $when,
        'date_end' => $when->copy()->addDays(2),
    ]);

    $component = Livewire::test('pages::dashboard');

    // Both plans belong to the agency's year.
    expect($component->instance()->months[3]['plans'])->toBe(2);

    $component->set('chartDivision', $division->id);

    // Only one of them reached this division.
    expect($component->instance()->months[3]['plans'])->toBe(1);
});

test('the chart names its two series', function () {
    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::dashboard')
        ->assertSee('Training completed')
        ->assertSee('LDI trainings held');
});
