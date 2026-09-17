<?php

use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use App\Models\User;
use Livewire\Livewire;

/**
 * Runs the download and gives back what the file would contain.
 *
 * @param  array<string, mixed>  $filters
 * @return list<list<string>>
 */
function downloadedRoster(array $filters = []): array
{
    $component = Livewire::test('pages::employees.index');

    foreach ($filters as $property => $value) {
        $component->set($property, $value);
    }

    $download = $component->call('exportCsv')->effects;

    $csv = base64_decode((string) data_get($download, 'download.content'));

    $lines = array_filter(explode("\n", str_replace(["\xEF\xBB\xBF", "\r"], '', $csv)));

    return array_map(fn (string $line): array => str_getcsv($line), $lines);
}

beforeEach(fn () => $this->actingAs(User::factory()->hr()->create()));

test('the roster downloads with a heading row and one line per employee', function () {
    $division = Division::factory()->create(['name' => 'Administrative']);
    $section = Section::factory()->for($division)->create(['name' => 'Records']);
    $position = Position::factory()->create(['title' => 'Administrative Officer III']);

    Employee::factory()->for($section)->for($division)->for($position)->create([
        'employee_number' => 'EMP-777',
        'first_name' => 'Gabriela',
        'last_name' => 'Silang',
        'middle_name' => null,
    ]);

    $rows = downloadedRoster();

    expect($rows)->toHaveCount(2)
        ->and($rows[0][0])->toBe('employee_number')
        ->and($rows[1][0])->toBe('EMP-777')
        ->and($rows[1][1])->toBe('Silang')
        ->and($rows[1][2])->toBe('Gabriela')
        ->and($rows[1][6])->toBe('Administrative')
        ->and($rows[1][7])->toBe('Records')
        ->and($rows[1][8])->toBe('Administrative Officer III');
});

test('the file holds what the filters were showing, not the whole roster', function () {
    $wanted = Division::factory()->create(['name' => 'Finance']);
    $other = Division::factory()->create(['name' => 'RITD']);

    // The division on an employee follows their section, so the section
    // is what decides which division they land in.
    Employee::factory()->for(Section::factory()->for($wanted))->create(['last_name' => 'Kept']);
    Employee::factory()->for(Section::factory()->for($other))->create(['last_name' => 'Filtered out']);

    $rows = downloadedRoster(['divisionId' => $wanted->id]);

    expect($rows)->toHaveCount(2)
        ->and($rows[1][1])->toBe('Kept');
});

test('a section head downloads their own section and nobody else', function () {
    $division = Division::factory()->create();
    $theirs = Section::factory()->for($division)->create();
    $other = Section::factory()->for($division)->create();

    $head = User::factory()->sectionHead()->create();
    Employee::factory()->for($theirs)->for($division)->create(['user_id' => $head->id, 'last_name' => 'Head']);
    Employee::factory()->for($theirs)->for($division)->create(['last_name' => 'Theirs']);
    Employee::factory()->for($other)->for($division)->create(['last_name' => 'Somebody else']);

    $this->actingAs($head);

    $names = collect(downloadedRoster())->skip(1)->pluck(1);

    expect($names)->toContain('Theirs')
        ->and($names)->not->toContain('Somebody else');
});

test('somebody who has left the roster is left off the file', function () {
    Employee::factory()->create(['last_name' => 'Working', 'is_active' => true]);
    Employee::factory()->create(['last_name' => 'Departed', 'is_active' => false]);

    $names = collect(downloadedRoster())->skip(1)->pluck(1);

    expect($names)->toContain('Working')
        ->and($names)->not->toContain('Departed');
});
