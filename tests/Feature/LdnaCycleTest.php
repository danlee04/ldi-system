<?php

use App\Actions\Ldna\OpenLdnaCycle;
use App\Enums\ProficiencyLevel;
use App\Models\Competency;
use App\Models\Employee;
use App\Models\LdnaCycle;
use App\Models\Position;
use App\Models\Section;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * @return array<int, string> the required level copied in, keyed by competency id
 */
function copiedLevels(Employee $employee, LdnaCycle $cycle): array
{
    return assessmentOf($employee, $cycle)->ratings
        ->mapWithKeys(fn ($rating): array => [$rating->competency_id => $rating->required_level->value])
        ->all();
}

function openFor2027(): LdnaCycle
{
    return app(OpenLdnaCycle::class)->handle(User::factory()->hr()->create(), 2027, today()->subDay(), today()->addMonth());
}

test('opening asks core of everybody, leadership of heads, and technical by position', function () {
    $core = Competency::factory()->core(ProficiencyLevel::Intermediate)->create();
    $leadership = Competency::factory()->leadership(ProficiencyLevel::Advanced)->create();
    $technical = Competency::factory()->technical()->create();

    $nurse = Position::factory()->create(['title' => 'Nurse I']);
    $nurse->competencies()->attach($technical->id, ['required_level' => ProficiencyLevel::Superior->value]);

    $section = Section::factory()->create();
    $head = Employee::factory()->for($section)->create();
    $section->update(['section_head_employee_id' => $head->id]);
    $staffNurse = Employee::factory()->for($section)->create(['position_id' => $nurse->id]);

    $cycle = openFor2027();

    expect(copiedLevels($staffNurse, $cycle))->toEqual([
        $core->id => 'intermediate',
        $technical->id => 'superior',
    ])->and(copiedLevels($head, $cycle))->toEqual([
        $core->id => 'intermediate',
        $leadership->id => 'advanced',
    ]);
});

test('inactive employees and inactive competencies are left out', function () {
    Competency::factory()->core()->create();
    $retired = Competency::factory()->core()->inactive()->create();
    $gone = Employee::factory()->inactive()->create();
    $here = Employee::factory()->create();

    $cycle = openFor2027();

    expect($cycle->assessments()->where('employee_id', $gone->id)->exists())->toBeFalse()
        ->and(copiedLevels($here, $cycle))->not->toHaveKey($retired->id);
});

test('a cycle with nothing to assess people on is refused', function () {
    Competency::factory()->core()->inactive()->create();

    expect(fn () => openFor2027())->toThrow(ValidationException::class);
    expect(LdnaCycle::count())->toBe(0);
});

test('changing the framework after opening leaves the copied levels alone', function () {
    $core = Competency::factory()->core(ProficiencyLevel::Intermediate)->create();
    $technical = Competency::factory()->technical()->create();
    $position = Position::factory()->create();
    $position->competencies()->attach($technical->id, ['required_level' => ProficiencyLevel::Basic->value]);
    $employee = Employee::factory()->create(['position_id' => $position->id]);

    $cycle = openFor2027();

    $core->update(['required_level' => ProficiencyLevel::Superior]);
    $position->competencies()->updateExistingPivot($technical->id, ['required_level' => ProficiencyLevel::Superior->value]);

    expect(copiedLevels($employee, $cycle))->toEqual([
        $core->id => 'intermediate',
        $technical->id => 'basic',
    ]);
});

test('everybody with an account is told when it opens', function () {
    Competency::factory()->core()->create();
    $employee = Employee::factory()->create(['user_id' => User::factory()->employee()->create()->id]);
    Employee::factory()->create(['user_id' => null]);

    openFor2027();

    $told = $employee->user->unreadNotifications()->first();

    expect($told->data['kind'])->toBe('ldna_opened')
        ->and($told->data['title'])->toBe('LDNA 2027');
});

test('the bell names an ldna notification for what it is', function () {
    $user = User::factory()->employee()->create();
    Employee::factory()->create(['user_id' => $user->id]);
    Competency::factory()->core()->create();

    openFor2027();

    $this->actingAs($user);

    Livewire::test('notifications')->assertSee('Needs assessment is open')->assertSee('LDNA 2027');
});

test('a competency somebody was rated on cannot be deleted', function () {
    $this->actingAs(User::factory()->hr()->create());
    Employee::factory()->create();

    $cycle = openLdna();
    $competency = $cycle->assessments()->first()->ratings()->first()->competency;

    Livewire::test('pages::setup.competencies')->call('delete', $competency->id);

    expect(Competency::find($competency->id))->not->toBeNull();
});

test('a competency nobody was rated on can be deleted', function () {
    $this->actingAs(User::factory()->hr()->create());
    $competency = Competency::factory()->core()->withIndicators()->create();

    Livewire::test('pages::setup.competencies')->call('delete', $competency->id);

    expect(Competency::find($competency->id))->toBeNull();
});
