<?php

use App\Actions\Ldna\SaveSelfRating;
use App\Enums\ProficiencyLevel;
use App\Models\Competency;
use App\Models\Employee;
use App\Models\LdnaAssessment;
use App\Models\LdnaCycle;
use App\Models\Section;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * Somebody with an account, under a section head who has one too, in an
 * open cycle of two core competencies: Exemplifying integrity at Advanced,
 * and openLdna()'s Delivering service excellence at Intermediate.
 *
 * @return array{employee: Employee, head: User, cycle: LdnaCycle, assessment: LdnaAssessment}
 */
function selfRater(): array
{
    $section = Section::factory()->create();
    $headUser = User::factory()->sectionHead()->create();
    $head = Employee::factory()->for($section)->create(['user_id' => $headUser->id]);
    $section->update(['section_head_employee_id' => $head->id]);

    $employee = Employee::factory()->for($section)->create(['user_id' => User::factory()->employee()->create()->id]);

    Competency::factory()->core(ProficiencyLevel::Advanced)->withIndicators()->create(['name' => 'Exemplifying integrity']);
    $cycle = openLdna();

    return ['employee' => $employee, 'head' => $headUser, 'cycle' => $cycle, 'assessment' => assessmentOf($employee, $cycle)];
}

/**
 * @return array<int, string> every rating on the assessment at the same level
 */
function everyRatingAt(LdnaAssessment $assessment, string $level): array
{
    return $assessment->ratings()->pluck('id')->mapWithKeys(fn (int $id): array => [$id => $level])->all();
}

function selfRatedNotices(User $user): int
{
    return $user->notifications->filter(fn (DatabaseNotification $notice): bool => $notice->data['kind'] === 'ldna_self_rated')->count();
}

test('an employee saves part of their self-rating and finds it again', function () {
    ['employee' => $employee, 'assessment' => $assessment] = selfRater();
    $first = $assessment->ratings()->first();

    app(SaveSelfRating::class)->handle($employee->user, $assessment, [$first->id => 'basic']);

    expect($first->fresh()->self_level)->toBe(ProficiencyLevel::Basic)
        ->and($assessment->fresh()->isSelfSubmitted())->toBeFalse();

    $this->actingAs($employee->user);

    Livewire::test('pages::ldna.mine')->assertSet("levels.{$first->id}", 'basic');
});

test('submitting asks for every competency, and writes nothing when it is refused', function () {
    ['employee' => $employee, 'assessment' => $assessment] = selfRater();
    $first = $assessment->ratings()->first();

    expect(fn () => app(SaveSelfRating::class)->handle($employee->user, $assessment, [$first->id => 'basic'], submit: true))
        ->toThrow(ValidationException::class);

    expect($assessment->fresh()->isSelfSubmitted())->toBeFalse()
        ->and($first->fresh()->self_level)->toBeNull();
});

test('submitting tells the section head, and only the first time', function () {
    ['employee' => $employee, 'head' => $head, 'assessment' => $assessment] = selfRater();
    $save = app(SaveSelfRating::class);

    $save->handle($employee->user, $assessment, everyRatingAt($assessment, 'advanced'), submit: true);
    $save->handle($employee->user, $assessment->fresh(), everyRatingAt($assessment, 'superior'), submit: true);

    expect($assessment->fresh()->isSelfSubmitted())->toBeTrue()
        ->and(selfRatedNotices($head->fresh()))->toBe(1);
});

test('hr is told when nobody else rates them', function () {
    $hr = User::factory()->hr()->create();
    $employee = Employee::factory()->create(['user_id' => User::factory()->employee()->create()->id]);
    $assessment = assessmentOf($employee, openLdna());

    app(SaveSelfRating::class)->handle($employee->user, $assessment, everyRatingAt($assessment, 'basic'), submit: true);

    expect(selfRatedNotices($hr->fresh()))->toBe(1);
});

test('once submitted a level can be changed but not cleared', function () {
    ['employee' => $employee, 'assessment' => $assessment] = selfRater();
    $save = app(SaveSelfRating::class);
    $save->handle($employee->user, $assessment, everyRatingAt($assessment, 'basic'), submit: true);

    $first = $assessment->ratings()->first();

    expect(fn () => $save->handle($employee->user, $assessment->fresh(), [$first->id => '']))
        ->toThrow(ValidationException::class);

    $save->handle($employee->user, $assessment->fresh(), [$first->id => 'superior']);

    expect($first->fresh()->self_level)->toBe(ProficiencyLevel::Superior);
});

test('nobody else can fill in somebody else self-rating', function () {
    ['head' => $head, 'assessment' => $assessment] = selfRater();

    expect(fn () => app(SaveSelfRating::class)->handle($head, $assessment, everyRatingAt($assessment, 'basic')))
        ->toThrow(AuthorizationException::class);
});

test('a self-rating is refused outside the window', function (string $state) {
    ['employee' => $employee, 'cycle' => $cycle, 'assessment' => $assessment] = selfRater();

    $cycle->update($state === 'closed'
        ? ['opens_on' => today()->subMonths(2), 'closes_on' => today()->subDay()]
        : ['opens_on' => today()->addWeek(), 'closes_on' => today()->addMonth()]);

    expect(fn () => app(SaveSelfRating::class)->handle($employee->user, $assessment->fresh(), everyRatingAt($assessment, 'basic')))
        ->toThrow(AuthorizationException::class);
})->with(['closed', 'upcoming']);

test('a rating from somebody else assessment is refused', function () {
    ['employee' => $employee, 'cycle' => $cycle, 'assessment' => $assessment] = selfRater();
    $other = assessmentOf(Employee::query()->whereKeyNot($employee->id)->firstOrFail(), $cycle);

    expect(fn () => app(SaveSelfRating::class)->handle($employee->user, $assessment, [$other->ratings()->first()->id => 'basic']))
        ->toThrow(ValidationException::class);
});

test('the required level stays hidden until they submit', function () {
    ['employee' => $employee, 'assessment' => $assessment] = selfRater();
    $this->actingAs($employee->user);

    Livewire::test('pages::ldna.mine')->assertDontSee('Required: Advanced');

    Livewire::test('pages::ldna.mine')
        ->set('levels', everyRatingAt($assessment, 'basic'))
        ->call('submit')
        ->assertHasNoErrors();

    Livewire::test('pages::ldna.mine')->assertSee('Required: Advanced');
});

test('the supervisor rating and the gap appear only once the cycle closes', function () {
    ['employee' => $employee, 'cycle' => $cycle, 'assessment' => $assessment] = selfRater();
    $assessment->ratings()->update(['supervisor_level' => ProficiencyLevel::Basic->value]);
    $this->actingAs($employee->user);

    Livewire::test('pages::ldna.mine')->assertDontSee('Supervisor: Basic');

    $cycle->update(['opens_on' => today()->subMonths(2), 'closes_on' => today()->subDay()]);

    // Exemplifying integrity asks Advanced; Basic is two short of it.
    Livewire::test('pages::ldna.mine')->assertSee('Supervisor: Basic')->assertSee('2 levels short');
});

test('the page says when there is nothing to fill in', function () {
    $user = User::factory()->employee()->create();
    Employee::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test('pages::ldna.mine')->assertSee('No LDNA is open right now.');
});

test('somebody hired after it opened is told hr will add them', function () {
    $cycle = openLdna();
    $user = User::factory()->employee()->create();
    Employee::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test('pages::ldna.mine')->assertSee("You are not in LDNA {$cycle->year} yet.");
});

test('an employee is offered my ldna in the sidebar', function () {
    $user = User::factory()->employee()->create();
    Employee::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $this->get(route('dashboard'))->assertOk()->assertSee('My LDNA');
});
