<?php

use App\Enums\CompetencyType;
use App\Enums\ProficiencyLevel;
use App\Models\Competency;
use App\Models\User;
use Livewire\Livewire;

/**
 * A description for every level, as the form asks for them.
 *
 * @return array<string, string>
 */
function describedLevels(): array
{
    return collect(ProficiencyLevel::cases())
        ->mapWithKeys(fn (ProficiencyLevel $level): array => [$level->value => "At {$level->label()} level"])
        ->all();
}

beforeEach(fn () => $this->actingAs(User::factory()->hr()->create()));

test('hr adds a core competency with what each level looks like', function () {
    Livewire::test('pages::setup.competencies')
        ->call('create')
        ->set('name', 'Delivering service excellence')
        ->set('type', 'core')
        ->set('requiredLevel', 'intermediate')
        ->set('indicators', describedLevels())
        ->call('save')
        ->assertHasNoErrors();

    $competency = Competency::where('name', 'Delivering service excellence')->first();

    expect($competency->type)->toBe(CompetencyType::Core)
        ->and($competency->required_level)->toBe(ProficiencyLevel::Intermediate)
        ->and($competency->indicators)->toHaveCount(4)
        ->and($competency->indicatorFor(ProficiencyLevel::Superior))->toBe('At Superior level');
});

test('a technical competency keeps no level of its own', function () {
    Livewire::test('pages::setup.competencies')
        ->call('create')
        ->set('name', 'Crisis intervention')
        ->set('type', 'core')
        ->set('requiredLevel', 'advanced')
        ->set('type', 'technical')
        ->set('indicators', describedLevels())
        ->call('save')
        ->assertHasNoErrors();

    expect(Competency::where('name', 'Crisis intervention')->first()->required_level)->toBeNull();
});

test('a core competency must say what level it asks for', function () {
    Livewire::test('pages::setup.competencies')
        ->call('create')
        ->set('name', 'Exemplifying integrity')
        ->set('type', 'core')
        ->set('indicators', describedLevels())
        ->call('save')
        ->assertHasErrors('requiredLevel');
});

test('every level must be described', function () {
    $levels = describedLevels();
    $levels['superior'] = '';

    Livewire::test('pages::setup.competencies')
        ->call('create')
        ->set('name', 'Exemplifying integrity')
        ->set('type', 'core')
        ->set('requiredLevel', 'basic')
        ->set('indicators', $levels)
        ->call('save')
        ->assertHasErrors('indicators.superior');
});

test('editing rewrites a description without adding a fifth', function () {
    $competency = Competency::factory()->core()->withIndicators()->create();

    Livewire::test('pages::setup.competencies')
        ->call('edit', $competency->id)
        ->set('indicators.basic', 'Rewritten')
        ->call('save')
        ->assertHasNoErrors();

    expect($competency->indicators()->count())->toBe(4)
        ->and($competency->fresh()->indicatorFor(ProficiencyLevel::Basic))->toBe('Rewritten');
});

test('deactivating keeps the competency but takes it out of use', function () {
    $competency = Competency::factory()->core()->create();

    Livewire::test('pages::setup.competencies')->call('toggleActive', $competency->id);

    expect($competency->fresh()->is_active)->toBeFalse();
});

test('hr is offered competencies in the sidebar', function () {
    $this->get(route('dashboard'))->assertOk()->assertSee('Competencies');
});

test('a plain employee cannot open competencies', function () {
    $this->actingAs(User::factory()->employee()->create());

    $this->get(route('setup.competencies'))->assertForbidden();
});
