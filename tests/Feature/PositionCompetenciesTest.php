<?php

use App\Enums\ProficiencyLevel;
use App\Models\Competency;
use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * @return array<int, string> the level each competency is asked at, keyed by competency id
 */
function levelsSetOn(Position $position): array
{
    return DB::table('competency_position')
        ->where('position_id', $position->id)
        ->pluck('required_level', 'competency_id')
        ->all();
}

beforeEach(fn () => $this->actingAs(User::factory()->hr()->create()));

test('hr sets the technical competencies a position needs', function () {
    $position = Position::factory()->create();
    $needed = Competency::factory()->technical()->create();
    Competency::factory()->technical()->create();

    Livewire::test('pages::setup.positions')
        ->call('editCompetencies', $position->id)
        ->set("positionLevels.{$needed->id}", 'advanced')
        ->call('saveCompetencies')
        ->assertHasNoErrors();

    expect(levelsSetOn($position))->toBe([$needed->id => 'advanced']);
});

test('setting one back to not needed takes it off the position', function () {
    $position = Position::factory()->create();
    $competency = Competency::factory()->technical()->create();
    $position->competencies()->attach($competency->id, ['required_level' => ProficiencyLevel::Basic->value]);

    Livewire::test('pages::setup.positions')
        ->call('editCompetencies', $position->id)
        ->assertSet("positionLevels.{$competency->id}", 'basic')
        ->set("positionLevels.{$competency->id}", '')
        ->call('saveCompetencies')
        ->assertHasNoErrors();

    expect(levelsSetOn($position))->toBe([]);
});

test('only technical competencies are offered', function () {
    $position = Position::factory()->create();
    $core = Competency::factory()->core()->create();
    $technical = Competency::factory()->technical()->create();

    $component = Livewire::test('pages::setup.positions')->call('editCompetencies', $position->id);

    expect(array_keys($component->get('positionLevels')))->toBe([$technical->id])
        ->and(array_keys($component->get('positionLevels')))->not->toContain($core->id);
});

test('saving leaves alone a level set on a competency since deactivated', function () {
    $position = Position::factory()->create();
    $retired = Competency::factory()->technical()->inactive()->create();
    $position->competencies()->attach($retired->id, ['required_level' => ProficiencyLevel::Superior->value]);

    Livewire::test('pages::setup.positions')
        ->call('editCompetencies', $position->id)
        ->call('saveCompetencies')
        ->assertHasNoErrors();

    expect(levelsSetOn($position))->toBe([$retired->id => 'superior']);
});

test('a competency that stops being technical leaves every position', function () {
    $position = Position::factory()->create();
    $competency = Competency::factory()->technical()->withIndicators()->create();
    $position->competencies()->attach($competency->id, ['required_level' => ProficiencyLevel::Basic->value]);

    Livewire::test('pages::setup.competencies')
        ->call('edit', $competency->id)
        ->set('type', 'core')
        ->set('requiredLevel', 'basic')
        ->call('save')
        ->assertHasNoErrors();

    expect(levelsSetOn($position))->toBe([]);
});
