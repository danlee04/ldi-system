<?php

use App\Actions\Reports\LdnaGapReport;
use App\Enums\ProficiencyLevel;
use App\Models\Competency;
use App\Models\Division;
use App\Models\Employee;
use App\Models\LdiTraining;
use App\Models\LdnaCycle;
use App\Models\Section;
use App\Models\User;
use Livewire\Livewire;

/**
 * Sets every supervisor level on somebody's assessment at once.
 */
function supervisorRates(Employee $employee, LdnaCycle $cycle, ProficiencyLevel $level): void
{
    assessmentOf($employee, $cycle)->ratings()->update(['supervisor_level' => $level->value]);
}

/**
 * @return array<string, mixed> the report's row for openLdna()'s competency
 */
function serviceExcellenceRow(LdnaCycle $cycle, ?int $divisionId = null): array
{
    return collect(app(LdnaGapReport::class)->handle($cycle, $divisionId))
        ->firstWhere('competency', 'Delivering service excellence') ?? [];
}

test('it counts who was rated, who is short, and by how much on average', function () {
    // openLdna() asks Intermediate of everybody.
    [$short, $shorter, $fine] = Employee::factory()->count(3)->create()->all();
    $cycle = openLdna();

    supervisorRates($short, $cycle, ProficiencyLevel::Basic);        // one short
    supervisorRates($shorter, $cycle, ProficiencyLevel::Basic);      // one short
    supervisorRates($fine, $cycle, ProficiencyLevel::Superior);     // none

    $row = serviceExcellenceRow($cycle);

    expect($row['rated'])->toBe(3)
        ->and($row['with_gap'])->toBe(2)
        ->and($row['percentage'])->toBe(66.7)
        ->and($row['average_gap'])->toBe(1.0)
        ->and(collect($row['people'])->pluck('employee')->all())->not->toContain($fine->listing_name);
});

test('a change to the framework after the cycle does not move its gaps', function () {
    $employee = Employee::factory()->create();
    $cycle = openLdna();
    supervisorRates($employee, $cycle, ProficiencyLevel::Intermediate);

    Competency::where('name', 'Delivering service excellence')->first()
        ->update(['required_level' => ProficiencyLevel::Superior]);

    expect(serviceExcellenceRow($cycle)['with_gap'])->toBe(0);
});

test('unrated competencies and people who have left are not counted', function () {
    [$rated, $unrated, $leaver] = Employee::factory()->count(3)->create()->all();
    $cycle = openLdna();

    supervisorRates($rated, $cycle, ProficiencyLevel::Basic);
    supervisorRates($leaver, $cycle, ProficiencyLevel::Basic);
    $leaver->update(['is_active' => false]);

    expect(serviceExcellenceRow($cycle)['rated'])->toBe(1);
});

test('it counts only plans in the cycle year that carry the competency', function () {
    $employee = Employee::factory()->create();
    $cycle = openLdna();
    supervisorRates($employee, $cycle, ProficiencyLevel::Basic);
    $competency = Competency::where('name', 'Delivering service excellence')->first();

    $inYear = LdiTraining::factory()->create(['date_start' => "{$cycle->year}-04-01", 'date_end' => "{$cycle->year}-04-02"]);
    $yearBefore = LdiTraining::factory()->create(['date_start' => ($cycle->year - 1).'-04-01', 'date_end' => ($cycle->year - 1).'-04-02']);
    LdiTraining::factory()->create(['date_start' => "{$cycle->year}-05-01", 'date_end' => "{$cycle->year}-05-02"]); // untagged

    $inYear->competencies()->attach($competency->id);
    $yearBefore->competencies()->attach($competency->id);

    expect(serviceExcellenceRow($cycle)['plans'])->toBe(1);
});

test('a plan tagged only with a deactivated competency does not count', function () {
    $employee = Employee::factory()->create();
    $cycle = openLdna();
    supervisorRates($employee, $cycle, ProficiencyLevel::Basic);
    $competency = Competency::where('name', 'Delivering service excellence')->first();
    $competency->update(['is_active' => false]);

    $plan = LdiTraining::factory()->create(['date_start' => "{$cycle->year}-04-01", 'date_end' => "{$cycle->year}-04-02"]);
    $plan->competencies()->attach($competency->id);

    expect(serviceExcellenceRow($cycle)['plans'])->toBe(0);
});

test('it narrows to a division', function () {
    $division = Division::factory()->create();
    $inside = Employee::factory()->for(Section::factory()->for($division))->create();
    $outside = Employee::factory()->create();
    $cycle = openLdna();

    supervisorRates($inside, $cycle, ProficiencyLevel::Basic);
    supervisorRates($outside, $cycle, ProficiencyLevel::Basic);

    expect(serviceExcellenceRow($cycle, $division->id)['rated'])->toBe(1);
});

test('the competencies most people are short in come first', function () {
    Competency::factory()->core(ProficiencyLevel::Superior)->create(['name' => 'Exemplifying integrity']);
    [$first, $second] = Employee::factory()->count(2)->create()->all();
    $cycle = openLdna();

    // Advanced clears Intermediate but not Superior.
    supervisorRates($first, $cycle, ProficiencyLevel::Advanced);
    supervisorRates($second, $cycle, ProficiencyLevel::Advanced);

    expect(collect(app(LdnaGapReport::class)->handle($cycle))->pluck('competency')->all())
        ->toBe(['Exemplifying integrity', 'Delivering service excellence']);
});

test('the gaps tab points out a gap no plan answers', function () {
    $this->actingAs(User::factory()->hr()->create());
    $employee = Employee::factory()->create();
    $cycle = openLdna();
    supervisorRates($employee, $cycle, ProficiencyLevel::Basic);

    Livewire::test('pages::ldna.show', ['cycle' => $cycle])
        ->set('tab', 'gaps')
        ->assertSee('Delivering service excellence')
        ->assertSee('No plan');
});
