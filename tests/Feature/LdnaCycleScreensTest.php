<?php

use App\Enums\ProficiencyLevel;
use App\Models\Competency;
use App\Models\Employee;
use App\Models\LdnaCycle;
use App\Models\Position;
use App\Models\Section;
use App\Models\User;
use Livewire\Livewire;

test('hr sets up a cycle from the screen', function () {
    $this->actingAs(User::factory()->hr()->create());
    Competency::factory()->core()->create();
    Employee::factory()->count(2)->create();

    Livewire::test('pages::ldna.index')
        ->call('create')
        ->set('year', '2027')
        ->set('opensOn', today()->toDateString())
        ->set('closesOn', today()->addMonth()->toDateString())
        ->call('openCycle')
        ->assertHasNoErrors()
        ->assertRedirect(route('ldna.show', LdnaCycle::where('year', 2027)->first()));

    expect(LdnaCycle::where('year', 2027)->first()->assessments()->count())->toBe(2);
});

test('a second cycle for the same year is refused', function () {
    $this->actingAs(User::factory()->hr()->create());
    Competency::factory()->core()->create();
    LdnaCycle::factory()->create(['year' => 2027]);

    Livewire::test('pages::ldna.index')
        ->call('create')
        ->set('year', '2027')
        ->call('openCycle')
        ->assertHasErrors('year');
});

test('the screen says why a cycle cannot be set up without competencies', function () {
    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::ldna.index')
        ->call('create')
        ->set('year', '2027')
        ->call('openCycle')
        ->assertHasErrors('year');

    expect(LdnaCycle::count())->toBe(0);
});

test('progress shows who rates each person', function () {
    $this->actingAs(User::factory()->hr()->create());

    $section = Section::factory()->create();
    $head = Employee::factory()->for($section)->create(['last_name' => 'Abao']);
    $section->update(['section_head_employee_id' => $head->id]);
    $staff = Employee::factory()->for($section)->create();

    $cycle = openLdna();

    $raters = Livewire::test('pages::ldna.show', ['cycle' => $cycle])->instance()->raters;

    expect($raters[assessmentOf($staff, $cycle)->id])->toBe($head->listing_name)
        // The head has nobody above them in this division, so HR rates them.
        ->and($raters[assessmentOf($head, $cycle)->id])->toBe('HR');
});

test('progress flags a position with no technical competency and a person with no account', function () {
    $this->actingAs(User::factory()->hr()->create());
    Employee::factory()->create(['user_id' => null]);

    Livewire::test('pages::ldna.show', ['cycle' => openLdna()])
        ->assertSee('No technical')
        ->assertSee('No account');
});

test('the technical badge ignores a deactivated competency', function () {
    $this->actingAs(User::factory()->hr()->create());

    $deactivated = Competency::factory()->technical()->inactive()->create();
    $onlyDeactivated = Position::factory()->create();
    $onlyDeactivated->competencies()->attach($deactivated->id, ['required_level' => ProficiencyLevel::Basic->value]);

    $active = Competency::factory()->technical()->create();
    $withActive = Position::factory()->create();
    $withActive->competencies()->attach($active->id, ['required_level' => ProficiencyLevel::Basic->value]);

    Employee::factory()->create(['position_id' => $onlyDeactivated->id]);
    Employee::factory()->create(['position_id' => $withActive->id]);

    $cycle = openLdna();

    $flags = Livewire::test('pages::ldna.show', ['cycle' => $cycle])->instance()->positionsWithTechnical;

    expect($flags)->not->toHaveKey($onlyDeactivated->id)
        ->and($flags)->toHaveKey($withActive->id);
});

test('hr syncs new people from the show screen', function () {
    $this->actingAs(User::factory()->hr()->create());
    Employee::factory()->create();
    $cycle = openLdna();

    $newcomer = Employee::factory()->create(['user_id' => User::factory()->employee()->create()->id]);

    $component = Livewire::test('pages::ldna.show', ['cycle' => $cycle])
        ->call('confirmSync')
        ->call('syncCycle')
        ->assertHasNoErrors();

    expect(assessmentOf($newcomer, $cycle))->not->toBeNull()
        ->and($component->instance()->progress['people'])->toBe(2);
});

test('hr refreshes a moved person from the show screen', function () {
    $this->actingAs(User::factory()->hr()->create());

    $newCompetency = Competency::factory()->technical()->create();
    $before = Position::factory()->create();
    $after = Position::factory()->create();
    $after->competencies()->attach($newCompetency->id, ['required_level' => ProficiencyLevel::Basic->value]);

    $employee = Employee::factory()->create(['position_id' => $before->id]);
    $cycle = openLdna();
    $assessment = assessmentOf($employee, $cycle);

    $employee->update(['position_id' => $after->id]);

    Livewire::test('pages::ldna.show', ['cycle' => $cycle])
        ->call('confirmRefresh', $assessment->id)
        ->call('refreshAssessment')
        ->assertHasNoErrors();

    expect($assessment->ratings()->pluck('competency_id')->all())->toContain($newCompetency->id);
});

test('moving the closing date on reopens a closed cycle', function () {
    $this->actingAs(User::factory()->hr()->create());
    $cycle = LdnaCycle::factory()->closed()->create();

    Livewire::test('pages::ldna.show', ['cycle' => $cycle])
        ->call('editClosing')
        ->set('closesOn', today()->addWeek()->toDateString())
        ->call('extend')
        ->assertHasNoErrors();

    expect($cycle->fresh()->isOpen())->toBeTrue();
});

test('the closing date cannot come before the opening date', function () {
    $this->actingAs(User::factory()->hr()->create());
    $cycle = LdnaCycle::factory()->create();

    Livewire::test('pages::ldna.show', ['cycle' => $cycle])
        ->call('editClosing')
        ->set('closesOn', $cycle->opens_on->subDay()->toDateString())
        ->call('extend')
        ->assertHasErrors('closesOn');
});

test('only hr opens the ldna screens', function () {
    $cycle = LdnaCycle::factory()->create();
    $this->actingAs(User::factory()->sectionHead()->create());

    $this->get(route('ldna.index'))->assertForbidden();
    $this->get(route('ldna.show', $cycle))->assertForbidden();
});

test('hr is offered ldna in the sidebar', function () {
    $this->actingAs(User::factory()->hr()->create());

    $this->get(route('dashboard'))->assertOk()->assertSee(route('ldna.index'));
});
