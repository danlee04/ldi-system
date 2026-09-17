<?php

use App\Actions\Ldna\SetPositionCompetencies;
use App\Enums\ProficiencyLevel;
use App\Models\Competency;
use App\Models\Position;
use Illuminate\Support\Facades\DB;

/**
 * @return array<int, string> the level each competency is asked at, keyed by competency id
 */
function pivotLevelsOn(Position $position): array
{
    return DB::table('competency_position')
        ->where('position_id', $position->id)
        ->pluck('required_level', 'competency_id')
        ->all();
}

test('a level is written against the position, and a blank one takes it off again', function () {
    $position = Position::factory()->create();
    $competency = Competency::factory()->technical()->create();
    $offered = collect([$competency]);

    app(SetPositionCompetencies::class)->handle($position, $offered, [
        $competency->id => ProficiencyLevel::Advanced->value,
    ]);

    expect(pivotLevelsOn($position))->toBe([$competency->id => 'advanced']);

    app(SetPositionCompetencies::class)->handle($position, $offered, [$competency->id => '']);

    expect(pivotLevelsOn($position))->toBe([]);
});

test('a competency the form did not ask about is left alone, whatever level is handed in for it', function () {
    $position = Position::factory()->create();
    $asked = Competency::factory()->technical()->create();
    $retired = Competency::factory()->technical()->inactive()->create();

    $position->competencies()->attach($retired->id, ['required_level' => ProficiencyLevel::Superior->value]);

    app(SetPositionCompetencies::class)->handle($position, collect([$asked]), [
        $asked->id => ProficiencyLevel::Basic->value,
        $retired->id => ProficiencyLevel::Basic->value,
    ]);

    $levels = pivotLevelsOn($position);

    expect($levels[$retired->id])->toBe('superior')
        ->and($levels[$asked->id])->toBe('basic');
});
