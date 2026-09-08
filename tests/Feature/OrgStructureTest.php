<?php

use App\Models\Division;
use App\Models\Section;
use Illuminate\Database\QueryException;

test('a section belongs to a division', function () {
    $division = Division::factory()->create(['name' => 'Finance Division', 'code' => 'FAD']);
    $section = Section::factory()->for($division)->create(['name' => 'Human Resource', 'code' => 'HRS']);

    expect($section->division->code)->toBe('FAD')
        ->and($division->sections)->toHaveCount(1);
});

test('section codes are unique', function () {
    Section::factory()->create(['code' => 'HRS']);

    expect(fn () => Section::factory()->create(['code' => 'HRS']))
        ->toThrow(QueryException::class);
});
