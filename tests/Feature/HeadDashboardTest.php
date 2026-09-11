<?php

use App\Models\Division;
use App\Models\Employee;
use App\Models\EmployeeEligibility;
use App\Models\LdiTraining;
use App\Models\Section;
use App\Models\TrainingRecord;
use App\Models\User;
use Livewire\Livewire;

/**
 * A person designated head of a division, signed in, with a section of
 * their own inside it.
 *
 * @return array{head: Employee, division: Division, section: Section}
 */
function divisionHead(): array
{
    $division = Division::factory()->create(['name' => 'Rehabilitation']);
    $section = Section::factory()->create(['division_id' => $division->id, 'name' => 'Nursing Service']);

    $user = User::factory()->divisionHead()->create();
    $head = Employee::factory()->create(['user_id' => $user->id, 'section_id' => $section->id]);

    $division->update(['division_head_employee_id' => $head->id]);

    test()->actingAs($user);

    return ['head' => $head, 'division' => $division, 'section' => $section];
}

/**
 * A person designated head of one section, signed in.
 *
 * @return array{head: Employee, section: Section}
 */
function sectionHead(): array
{
    $division = Division::factory()->create();
    $section = Section::factory()->create(['division_id' => $division->id, 'name' => 'Records Section']);

    $user = User::factory()->sectionHead()->create();
    $head = Employee::factory()->create(['user_id' => $user->id, 'section_id' => $section->id]);

    $section->update(['section_head_employee_id' => $head->id]);

    test()->actingAs($user);

    return ['head' => $head, 'section' => $section];
}

function trainedThisYear(Employee $employee): TrainingRecord
{
    return TrainingRecord::factory()->approved()->for($employee)->create([
        'date_start' => now()->startOfYear()->addMonth(),
        'date_end' => now()->startOfYear()->addMonth()->addDays(2),
        'hours' => 16,
    ]);
}

test('a division head sees their whole division and nobody outside it', function () {
    ['division' => $division, 'section' => $section] = divisionHead();

    // Somebody in another section of the same division, who still counts.
    $other = Section::factory()->create(['division_id' => $division->id]);
    Employee::factory()->count(2)->create(['section_id' => $other->id]);
    Employee::factory()->count(2)->create(['section_id' => $section->id]);

    // And a stranger in another division entirely.
    Employee::factory()->create(['section_id' => Section::factory()->create()->id]);

    $component = Livewire::test('pages::dashboard');

    // Two sections of two each, plus the head themselves.
    expect($component->instance()->seesTeam)->toBeTrue()
        ->and($component->instance()->teamTotals['people'])->toBe(5);

    $component->assertSee('My team')->assertSee('Rehabilitation');
});

test('a section head sees their section and not the one beside it', function () {
    ['section' => $section] = sectionHead();

    Employee::factory()->count(3)->create(['section_id' => $section->id]);

    // Same division, different section.
    $beside = Section::factory()->create(['division_id' => $section->division_id]);
    Employee::factory()->count(4)->create(['section_id' => $beside->id]);

    // Three, plus the head.
    expect(Livewire::test('pages::dashboard')->instance()->teamTotals['people'])->toBe(4);
});

test('the team is read from the designation, not the role', function () {
    ['head' => $head, 'section' => $section] = sectionHead();

    // The role still says section head, but somebody else holds the
    // section now — exactly the case the approvals queue already handles.
    $section->update(['section_head_employee_id' => Employee::factory()->create()->id]);

    expect(Livewire::test('pages::dashboard')->instance()->seesTeam)->toBeFalse();
});

test('an ordinary employee is shown no team', function () {
    $user = User::factory()->employee()->create();
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertDontSee('My team')
        ->assertDontSee('Not trained yet this year');
});

test('hr keeps the whole agency even when it also heads a section', function () {
    $section = Section::factory()->create();

    $user = User::factory()->hr()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id, 'section_id' => $section->id]);
    $section->update(['section_head_employee_id' => $employee->id]);

    $this->actingAs($user);

    $component = Livewire::test('pages::dashboard');

    expect($component->instance()->seesTeam)->toBeFalse()
        ->and($component->instance()->seesAgency)->toBeTrue();
});

test('the figures count coverage and hours from the team only', function () {
    ['head' => $head, 'section' => $section] = sectionHead();

    $trained = Employee::factory()->create(['section_id' => $section->id]);
    Employee::factory()->create(['section_id' => $section->id]);
    trainedThisYear($trained);

    // Somebody else's training does not count toward this team.
    trainedThisYear(Employee::factory()->create(['section_id' => Section::factory()->create()->id]));

    $totals = Livewire::test('pages::dashboard')->instance()->teamTotals;

    // The head and one other have nothing yet; one of three is trained.
    expect($totals['people'])->toBe(3)
        ->and($totals['covered'])->toBe(1)
        ->and($totals['percentage'])->toBe(33)
        ->and($totals['hours'])->toBe(16);
});

test('the untrained list names who has nothing yet this year', function () {
    ['section' => $section] = sectionHead();

    $done = Employee::factory()->create(['section_id' => $section->id, 'last_name' => 'Abao']);
    $waiting = Employee::factory()->create(['section_id' => $section->id, 'last_name' => 'Zamora']);

    trainedThisYear($done);

    // Last year's training does not make somebody trained this year.
    TrainingRecord::factory()->approved()->for($waiting)->create([
        'date_start' => now()->subYear(),
        'date_end' => now()->subYear()->addDays(2),
    ]);

    $untrained = Livewire::test('pages::dashboard')->instance()->teamUntrained;

    expect($untrained->pluck('id'))->toContain($waiting->id)
        ->and($untrained->pluck('id'))->not->toContain($done->id);
});

test('a division head is given coverage section by section', function () {
    ['division' => $division, 'section' => $section] = divisionHead();

    $other = Section::factory()->create(['division_id' => $division->id, 'name' => 'Admin Section']);
    trainedThisYear(Employee::factory()->create(['section_id' => $other->id]));

    $rows = collect(Livewire::test('pages::dashboard')->instance()->teamCoverage);

    expect($rows->pluck('division')->all())->toBe(['Admin Section', 'Nursing Service'])
        ->and($rows->firstWhere('division', 'Admin Section')['percentage'])->toBe(100.0);
});

test('a section head is not given a coverage chart of one bar', function () {
    sectionHead();

    expect(Livewire::test('pages::dashboard')->instance()->teamCoverage)->toBe([]);
});

test('the chart narrows to a section only inside the head own team', function () {
    ['division' => $division, 'section' => $section] = divisionHead();

    trainedThisYear(Employee::factory()->create(['section_id' => $section->id]));

    // A section of somebody else's division, asked for through the link.
    $foreign = Section::factory()->create();
    trainedThisYear(Employee::factory()->create(['section_id' => $foreign->id]));

    $component = Livewire::test('pages::dashboard');

    expect(collect($component->instance()->teamMonths)->sum('attendances'))->toBe(1);

    $component->set('teamSection', $foreign->id);

    // Nothing leaks in: the foreign section is not the head's to see.
    expect(collect($component->instance()->teamMonths)->sum('attendances'))->toBe(0);
});

test('eligibility alerts on the team leave everybody else out', function () {
    ['section' => $section] = sectionHead();

    $mine = Employee::factory()->create(['section_id' => $section->id]);
    $stranger = Employee::factory()->create(['section_id' => Section::factory()->create()->id]);

    EmployeeEligibility::factory()->for($mine)->create(['date_of_validity' => now()->addMonth()]);
    EmployeeEligibility::factory()->for($stranger)->create(['date_of_validity' => now()->addMonth()]);

    $alerts = Livewire::test('pages::dashboard')->instance()->teamEligibilityAlerts;

    expect($alerts->pluck('employee_id')->all())->toBe([$mine->id]);
});

test('a head is shown no money', function () {
    sectionHead();

    Livewire::test('pages::dashboard')
        ->assertDontSee('Spent in')
        ->assertDontSee('funded by HR');
});

test('a division head reads coverage by section, not by division', function () {
    divisionHead();

    Livewire::test('pages::dashboard')
        ->assertSee('Training coverage by section')
        ->assertDontSee('Training coverage by division');
});

test('a head is not shown their own record on the dashboard', function () {
    sectionHead();

    // It lives on My profile and My trainings; the dashboard is the team's.
    Livewire::test('pages::dashboard')
        ->assertDontSee('My own')
        ->assertDontSee('My pending trainings')
        ->assertDontSee('Where my submissions stand');
});

test('an employee who heads nothing still sees their own record', function () {
    $user = User::factory()->employee()->create();
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('My pending trainings')
        ->assertSee('Where my submissions stand');
});

test('a head gets quick stats counted over their own people', function () {
    ['section' => $section] = sectionHead();

    $mine = Employee::factory()->create(['section_id' => $section->id]);

    $plan = LdiTraining::factory()->create([
        'date_start' => now()->startOfYear()->addMonth(),
        'date_end' => now()->startOfYear()->addMonth()->addDays(2),
    ]);

    TrainingRecord::factory()->approved()->for($mine)->create([
        'ldi_training_id' => $plan->id,
        'date_start' => now()->startOfYear()->addMonth(),
        'date_end' => now()->startOfYear()->addMonth()->addDays(2),
        'cpd_units' => 4.5,
    ]);

    // A plan nobody on the team went to, and a stranger on the same terms.
    LdiTraining::factory()->create(['date_start' => now(), 'date_end' => now()]);
    Employee::factory()->create(['section_id' => Section::factory()->create()->id]);

    $stats = Livewire::test('pages::dashboard')
        ->assertSee('Quick stats')
        ->assertSee('LDI trainings attended in '.now()->year)
        ->instance()->teamQuickStats;

    // The head and one other, both on the factory's permanent terms.
    expect(array_sum($stats['statuses']))->toBe(2)
        ->and($stats['plans'])->toBe(1)
        ->and($stats['extra'][0]['value'])->toBe('4.5');
});
