<?php

use App\Actions\Ldna\CountLdnaRatingsDue;
use App\Actions\Ldna\SaveSelfRating;
use App\Actions\Ldna\SaveSupervisorRating;
use App\Enums\ProficiencyLevel;
use App\Models\Competency;
use App\Models\Employee;
use App\Models\LdnaAssessment;
use App\Models\LdnaCycle;
use App\Models\Section;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * Somebody with an account under a section head who has one too, in an
 * open cycle of two core competencies.
 *
 * @return array{employee: Employee, head: User, headEmployee: Employee, section: Section, cycle: LdnaCycle, assessment: LdnaAssessment}
 */
function underRatingHead(): array
{
    $section = Section::factory()->create();
    $headUser = User::factory()->sectionHead()->create();
    $head = Employee::factory()->for($section)->create(['user_id' => $headUser->id]);
    $section->update(['section_head_employee_id' => $head->id]);

    $employee = Employee::factory()->for($section)->create(['user_id' => User::factory()->employee()->create()->id]);

    Competency::factory()->core(ProficiencyLevel::Advanced)->withIndicators()->create(['name' => 'Exemplifying integrity']);
    $cycle = openLdna();

    return [
        'employee' => $employee,
        'head' => $headUser,
        'headEmployee' => $head,
        'section' => $section,
        'cycle' => $cycle,
        'assessment' => assessmentOf($employee, $cycle),
    ];
}

/**
 * @return array<int, string> every rating on the assessment at the same level
 */
function levelsAllAt(LdnaAssessment $assessment, string $level): array
{
    return $assessment->ratings()->pluck('id')->mapWithKeys(fn (int $id): array => [$id => $level])->all();
}

test('a section head rates their person before the person has rated themselves', function () {
    ['head' => $head, 'assessment' => $assessment] = underRatingHead();

    app(SaveSupervisorRating::class)->handle($head, $assessment, levelsAllAt($assessment, 'basic'), submit: true);

    $assessment->refresh();

    expect($assessment->isRated())->toBeTrue()
        ->and($assessment->rated_by)->toBe($head->id)
        ->and($assessment->isSelfSubmitted())->toBeFalse()
        ->and($assessment->ratings()->whereNull('supervisor_level')->exists())->toBeFalse();
});

test('submitting a rating asks for every competency', function () {
    ['head' => $head, 'assessment' => $assessment] = underRatingHead();
    $first = $assessment->ratings()->first();

    expect(fn () => app(SaveSupervisorRating::class)->handle($head, $assessment, [$first->id => 'basic'], submit: true))
        ->toThrow(ValidationException::class);

    expect($assessment->fresh()->isRated())->toBeFalse();
});

test('remarks are kept beside the level', function () {
    ['head' => $head, 'assessment' => $assessment] = underRatingHead();
    $first = $assessment->ratings()->first();

    app(SaveSupervisorRating::class)->handle($head, $assessment, [$first->id => 'basic'], [$first->id => 'Needs coaching on the SOP.']);

    expect($first->fresh()->remarks)->toBe('Needs coaching on the SOP.');
});

test('the head of another section cannot rate', function () {
    ['assessment' => $assessment] = underRatingHead();

    $otherSection = Section::factory()->create();
    $otherHead = User::factory()->sectionHead()->create();
    $otherSection->update(['section_head_employee_id' => Employee::factory()->for($otherSection)->create(['user_id' => $otherHead->id])->id]);

    expect(fn () => app(SaveSupervisorRating::class)->handle($otherHead, $assessment, levelsAllAt($assessment, 'basic')))
        ->toThrow(AuthorizationException::class);

    $this->actingAs($otherHead);
    $this->get(route('ldna.rate', $assessment))->assertForbidden();
});

test('hr rates somebody nobody else rates, and nobody a head rates', function () {
    $hr = User::factory()->hr()->create();
    // Made before the cycle opens, so that it enrols them.
    $lonerEmployee = Employee::factory()->create();
    ['assessment' => $headed, 'cycle' => $cycle] = underRatingHead();
    $loner = assessmentOf($lonerEmployee, $cycle);

    app(SaveSupervisorRating::class)->handle($hr, $loner, levelsAllAt($loner, 'basic'), submit: true);

    expect($loner->fresh()->isRated())->toBeTrue()
        ->and(fn () => app(SaveSupervisorRating::class)->handle($hr, $headed, levelsAllAt($headed, 'basic')))
        ->toThrow(AuthorizationException::class);
});

test('when the head changes, the new head rates and the old one cannot', function () {
    ['head' => $oldHead, 'section' => $section, 'assessment' => $assessment] = underRatingHead();

    $newHead = User::factory()->sectionHead()->create();
    $section->update(['section_head_employee_id' => Employee::factory()->for($section)->create(['user_id' => $newHead->id])->id]);

    expect(fn () => app(SaveSupervisorRating::class)->handle($oldHead, $assessment->fresh(), levelsAllAt($assessment, 'basic')))
        ->toThrow(AuthorizationException::class);

    app(SaveSupervisorRating::class)->handle($newHead, $assessment->fresh(), levelsAllAt($assessment, 'basic'), submit: true);

    expect($assessment->fresh()->rated_by)->toBe($newHead->id);
});

test('a closed cycle takes no more ratings', function () {
    ['head' => $head, 'cycle' => $cycle, 'assessment' => $assessment] = underRatingHead();
    $cycle->update(['opens_on' => today()->subMonths(2), 'closes_on' => today()->subDay()]);

    expect(fn () => app(SaveSupervisorRating::class)->handle($head, $assessment->fresh(), levelsAllAt($assessment, 'basic')))
        ->toThrow(AuthorizationException::class);
});

test('the list and the badge hold the head own people, less those already rated', function () {
    ['head' => $head, 'employee' => $employee, 'cycle' => $cycle, 'assessment' => $assessment] = underRatingHead();
    Employee::factory()->create(); // somebody in another section entirely

    $counter = app(CountLdnaRatingsDue::class);

    expect($counter->ratees($head, $cycle)->pluck('employee_id')->all())->toBe([$employee->id])
        ->and($counter->handle($head))->toBe(1);

    app(SaveSupervisorRating::class)->handle($head, $assessment, levelsAllAt($assessment, 'basic'), submit: true);

    expect(app(CountLdnaRatingsDue::class)->handle($head))->toBe(0);

    $this->actingAs($head);
    $this->get(route('dashboard'))->assertOk()->assertSee('LDNA ratings');
    Livewire::test('pages::ldna.ratings')->assertSee($employee->listing_name);
});

test('the rating page shows the self-rating only once it is submitted', function () {
    ['head' => $head, 'employee' => $employee, 'assessment' => $assessment] = underRatingHead();
    $this->actingAs($head);

    app(SaveSelfRating::class)->handle($employee->user, $assessment, levelsAllAt($assessment, 'superior'));

    Livewire::test('pages::ldna.rate', ['assessment' => $assessment])->assertDontSee('Self: Superior');

    app(SaveSelfRating::class)->handle($employee->user, $assessment->fresh(), levelsAllAt($assessment, 'superior'), submit: true);

    Livewire::test('pages::ldna.rate', ['assessment' => $assessment->fresh()])->assertSee('Self: Superior');
});

test('a head submits from the rating page', function () {
    ['head' => $head, 'assessment' => $assessment] = underRatingHead();
    $this->actingAs($head);

    Livewire::test('pages::ldna.rate', ['assessment' => $assessment])
        ->set('levels', levelsAllAt($assessment, 'advanced'))
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('ldna.ratings'));

    expect($assessment->fresh()->isRated())->toBeTrue();
});

test('hr progress offers rate where hr is the one to rate', function () {
    $this->actingAs(User::factory()->hr()->create());
    $loner = Employee::factory()->create();
    $cycle = openLdna();

    Livewire::test('pages::ldna.show', ['cycle' => $cycle])
        ->assertSee(route('ldna.rate', assessmentOf($loner, $cycle)));
});
