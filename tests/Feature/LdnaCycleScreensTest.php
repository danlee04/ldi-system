<?php

use App\Actions\Ldna\DeleteCompetency;
use App\Enums\ProficiencyLevel;
use App\Models\Competency;
use App\Models\Employee;
use App\Models\LdnaAssessment;
use App\Models\LdnaCycle;
use App\Models\LdnaRating;
use App\Models\Position;
use App\Models\Section;
use App\Models\User;
use App\Notifications\LdnaCycleOpened;
use App\Notifications\SelfRatingSubmitted;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
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

test('progress shows who confirms each person', function () {
    $this->actingAs(User::factory()->hr()->create());

    $section = Section::factory()->create();
    $head = Employee::factory()->for($section)->create(['last_name' => 'Abao']);
    $section->update(['section_head_employee_id' => $head->id]);
    $staff = Employee::factory()->for($section)->create();

    $cycle = openLdna();

    $confirmers = Livewire::test('pages::ldna.show', ['cycle' => $cycle])->instance()->confirmers;

    expect($confirmers[assessmentOf($staff, $cycle)->id])->toBe($head->listing_name)
        // The head has nobody above them in this division, so HR does.
        ->and($confirmers[assessmentOf($head, $cycle)->id])->toBe('HR');
});

test('the show screen shares one LdnaConfirmer between confirmers and hrConfirms', function () {
    $this->actingAs(User::factory()->hr()->create());

    $section = Section::factory()->create();
    $head = Employee::factory()->for($section)->create();
    $section->update(['section_head_employee_id' => $head->id]);
    Employee::factory()->for($section)->create();

    $cycle = openLdna();

    $activeEmployeeQueries = 0;

    DB::listen(function (QueryExecuted $query) use (&$activeEmployeeQueries): void {
        if (str_contains($query->sql, 'select "id" from "employees" where "is_active"')) {
            $activeEmployeeQueries++;
        }
    });

    Livewire::test('pages::ldna.show', ['cycle' => $cycle]);

    // One LdnaConfirmer, shared by confirmers() and hrConfirms(), so the
    // active-employee set it memoises is only ever queried once per render.
    expect($activeEmployeeQueries)->toBe(1);
});

test('the print heading only claims gaps on the gaps tab', function () {
    $this->actingAs(User::factory()->hr()->create());
    Employee::factory()->create();
    $cycle = openLdna();

    Livewire::test('pages::ldna.show', ['cycle' => $cycle])
        ->set('tab', 'progress')
        ->assertDontSee("LDNA {$cycle->year} — gaps");

    Livewire::test('pages::ldna.show', ['cycle' => $cycle])
        ->set('tab', 'gaps')
        ->assertSee("LDNA {$cycle->year} — gaps");
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

test('hr deletes a cycle with everything in it and the notices it sent', function () {
    $hr = User::factory()->hr()->create();
    $this->actingAs($hr);

    $cycle = LdnaCycle::factory()->create(['year' => 2027]);
    $assessment = LdnaAssessment::factory()->for($cycle, 'cycle')->create(['self_submitted_at' => now()]);
    $rating = LdnaRating::factory()->for($assessment, 'assessment')->create();

    $hr->notify(new LdnaCycleOpened($cycle));
    $hr->notify(new SelfRatingSubmitted($assessment));

    // Another year's notice is somebody else's business and stays.
    $hr->notify(new LdnaCycleOpened(LdnaCycle::factory()->create(['year' => 2028])));

    Livewire::test('pages::ldna.index')
        ->call('confirmDelete', $cycle->id)
        ->call('deleteCycle')
        ->assertHasNoErrors();

    expect(LdnaCycle::find($cycle->id))->toBeNull()
        ->and(LdnaAssessment::find($assessment->id))->toBeNull()
        ->and(LdnaRating::find($rating->id))->toBeNull()
        ->and($hr->notifications()->count())->toBe(1);
});

test('a cycle a head has confirmed in is deleted only once its year is typed', function () {
    $this->actingAs(User::factory()->hr()->create());

    $cycle = LdnaCycle::factory()->create(['year' => 2027]);
    LdnaAssessment::factory()->for($cycle, 'cycle')->create(['self_submitted_at' => now(), 'confirmed_at' => now()]);

    $component = Livewire::test('pages::ldna.index')
        ->call('confirmDelete', $cycle->id)
        ->assertSee('Type "2027" to delete it anyway')
        ->call('deleteCycle')
        ->assertHasErrors('typedYear')
        ->set('typedYear', '2026')
        ->call('deleteCycle')
        ->assertHasErrors('typedYear');

    expect(LdnaCycle::find($cycle->id))->not->toBeNull();

    $component->set('typedYear', '2027')->call('deleteCycle')->assertHasNoErrors();

    expect(LdnaCycle::find($cycle->id))->toBeNull();
});

test('a competency rated only in a deleted cycle can then be deleted', function () {
    $this->actingAs(User::factory()->hr()->create());

    $competency = Competency::factory()->core()->create();
    $cycle = LdnaCycle::factory()->create();
    LdnaRating::factory()
        ->for(LdnaAssessment::factory()->for($cycle, 'cycle'), 'assessment')
        ->create(['competency_id' => $competency->id]);

    expect(app(DeleteCompetency::class)->handle($competency))->toBeFalse();

    Livewire::test('pages::ldna.index')
        ->call('confirmDelete', $cycle->id)
        ->call('deleteCycle');

    expect(app(DeleteCompetency::class)->handle($competency->fresh()))->toBeTrue();
});
