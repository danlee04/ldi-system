<?php

use App\Actions\Ldna\DeleteCompetency;
use App\Actions\Ldna\SaveCompetency;
use App\Enums\CompetencyType;
use App\Enums\ProficiencyLevel;
use App\Models\Competency;
use App\Models\LdnaRating;
use App\Models\Position;

/**
 * The fields the form would have validated and handed to the action.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function competencyAttributes(array $overrides = []): array
{
    return [
        'name' => 'Delivering service excellence',
        'description' => '',
        'type' => CompetencyType::Core->value,
        'requiredLevel' => ProficiencyLevel::Intermediate->value,
        ...$overrides,
    ];
}

/**
 * One description per level, as the four boxes on the form give them.
 *
 * @return array<string, string>
 */
function competencyIndicators(string $prefix = 'At'): array
{
    return collect(ProficiencyLevel::cases())
        ->mapWithKeys(fn (ProficiencyLevel $level): array => [$level->value => "{$prefix} {$level->label()} level"])
        ->all();
}

test('a level of its own is kept for core and leadership, and never for technical', function () {
    $core = app(SaveCompetency::class)->handle(null, competencyAttributes(), competencyIndicators());

    $technical = app(SaveCompetency::class)->handle(null, competencyAttributes([
        'name' => 'Crisis intervention',
        'type' => CompetencyType::Technical->value,
        'requiredLevel' => ProficiencyLevel::Advanced->value,
    ]), competencyIndicators());

    expect($core->required_level)->toBe(ProficiencyLevel::Intermediate)
        ->and($technical->required_level)->toBeNull();
});

test('a technical competency that becomes core lets go of the positions it was set against', function () {
    $competency = Competency::factory()->technical()->create();
    $position = Position::factory()->create();

    $position->competencies()->attach($competency, ['required_level' => ProficiencyLevel::Advanced->value]);

    app(SaveCompetency::class)->handle($competency, competencyAttributes([
        'name' => $competency->name,
        'type' => CompetencyType::Core->value,
    ]), competencyIndicators());

    expect($position->competencies()->count())->toBe(0);
});

test('a technical competency keeps the positions it is set against', function () {
    $competency = Competency::factory()->technical()->create();
    $position = Position::factory()->create();

    $position->competencies()->attach($competency, ['required_level' => ProficiencyLevel::Advanced->value]);

    app(SaveCompetency::class)->handle($competency, competencyAttributes([
        'name' => $competency->name,
        'type' => CompetencyType::Technical->value,
    ]), competencyIndicators());

    expect($position->competencies()->count())->toBe(1);
});

test('saving again rewrites the four descriptions instead of adding more', function () {
    $competency = app(SaveCompetency::class)->handle(null, competencyAttributes(), competencyIndicators());

    app(SaveCompetency::class)->handle($competency, competencyAttributes(), competencyIndicators('Now at'));

    expect($competency->indicators()->count())->toBe(4)
        ->and($competency->fresh()->indicatorFor(ProficiencyLevel::Basic))->toBe('Now at Basic level');
});

test('an empty description is stored as nothing rather than as an empty string', function () {
    $competency = app(SaveCompetency::class)->handle(null, competencyAttributes(), competencyIndicators());

    expect($competency->description)->toBeNull();
});

test('a competency nobody has been assessed on is deleted', function () {
    $competency = Competency::factory()->core()->create();

    expect(app(DeleteCompetency::class)->handle($competency))->toBeTrue()
        ->and(Competency::whereKey($competency->getKey())->exists())->toBeFalse();
});

test('deleting is refused once somebody has been assessed on it', function () {
    $competency = Competency::factory()->core()->create();

    LdnaRating::factory()->for($competency)->create();

    expect(app(DeleteCompetency::class)->handle($competency))->toBeFalse()
        ->and(Competency::whereKey($competency->getKey())->exists())->toBeTrue();
});
