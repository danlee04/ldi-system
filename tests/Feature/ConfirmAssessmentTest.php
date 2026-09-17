<?php

use App\Actions\Ldna\ConfirmLdnaAssessment;
use App\Actions\Ldna\CountLdnaConfirmationsDue;
use App\Actions\Ldna\SaveSelfRating;
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
 * open cycle of one core competency.
 *
 * @return array{employee: Employee, head: User, headEmployee: Employee, section: Section, cycle: LdnaCycle, assessment: LdnaAssessment}
 */
function underConfirmingHead(): array
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

/**
 * The person answers and submits.
 */
function selfSubmit(LdnaAssessment $assessment, Employee $employee, string $level = 'basic'): void
{
    app(SaveSelfRating::class)->handle($employee->user, $assessment, levelsAllAt($assessment, $level), submit: true);
}

test('a head confirms what their person said about themselves', function () {
    ['head' => $head, 'employee' => $employee, 'assessment' => $assessment] = underConfirmingHead();

    selfSubmit($assessment, $employee);

    app(ConfirmLdnaAssessment::class)->handle($head, $assessment->fresh(), confirm: true);

    $assessment->refresh();

    expect($assessment->isConfirmed())->toBeTrue()
        ->and($assessment->confirmed_by)->toBe($head->id);
});

test('nothing can be confirmed before the person has submitted it', function () {
    ['head' => $head, 'assessment' => $assessment] = underConfirmingHead();

    expect(fn () => app(ConfirmLdnaAssessment::class)->handle($head, $assessment, confirm: true))
        ->toThrow(ValidationException::class);

    expect($assessment->fresh()->isConfirmed())->toBeFalse();
});

test('confirming leaves the levels exactly as the person gave them', function () {
    ['head' => $head, 'employee' => $employee, 'assessment' => $assessment] = underConfirmingHead();

    selfSubmit($assessment, $employee, 'basic');

    app(ConfirmLdnaAssessment::class)->handle($head, $assessment->fresh(), confirm: true);

    expect($assessment->ratings()->pluck('self_level')->unique()->all())->toBe([ProficiencyLevel::Basic]);
});

test('remarks are kept beside the level', function () {
    ['head' => $head, 'assessment' => $assessment] = underConfirmingHead();
    $first = $assessment->ratings()->first();

    app(ConfirmLdnaAssessment::class)->handle($head, $assessment, [$first->id => 'Needs coaching on the SOP.']);

    expect($first->fresh()->remarks)->toBe('Needs coaching on the SOP.');
});

test('the head of another section cannot confirm', function () {
    ['assessment' => $assessment] = underConfirmingHead();

    $otherSection = Section::factory()->create();
    $otherHead = User::factory()->sectionHead()->create();
    $otherSection->update(['section_head_employee_id' => Employee::factory()->for($otherSection)->create(['user_id' => $otherHead->id])->id]);

    expect(fn () => app(ConfirmLdnaAssessment::class)->handle($otherHead, $assessment))
        ->toThrow(AuthorizationException::class);

    $this->actingAs($otherHead);
    $this->get(route('ldna.review', $assessment))->assertForbidden();
});

test('hr confirms somebody nobody else confirms, and nobody a head confirms', function () {
    $hr = User::factory()->hr()->create();
    // Made before the cycle opens, so that it enrols them.
    $lonerEmployee = Employee::factory()->create();
    ['assessment' => $headed, 'cycle' => $cycle] = underConfirmingHead();
    $loner = assessmentOf($lonerEmployee, $cycle);

    app(ConfirmLdnaAssessment::class)->handle($hr, $loner);

    expect(fn () => app(ConfirmLdnaAssessment::class)->handle($hr, $headed))
        ->toThrow(AuthorizationException::class);
});

test('when the head changes, the new head confirms and the old one cannot', function () {
    ['head' => $oldHead, 'section' => $section, 'employee' => $employee, 'assessment' => $assessment] = underConfirmingHead();

    selfSubmit($assessment, $employee);

    $newHead = User::factory()->sectionHead()->create();
    $section->update(['section_head_employee_id' => Employee::factory()->for($section)->create(['user_id' => $newHead->id])->id]);

    expect(fn () => app(ConfirmLdnaAssessment::class)->handle($oldHead, $assessment->fresh()))
        ->toThrow(AuthorizationException::class);

    app(ConfirmLdnaAssessment::class)->handle($newHead, $assessment->fresh(), confirm: true);

    expect($assessment->fresh()->confirmed_by)->toBe($newHead->id);
});

test('a closed cycle takes no more confirmations', function () {
    ['head' => $head, 'cycle' => $cycle, 'assessment' => $assessment] = underConfirmingHead();
    $cycle->update(['opens_on' => today()->subMonths(2), 'closes_on' => today()->subDay()]);

    expect(fn () => app(ConfirmLdnaAssessment::class)->handle($head, $assessment->fresh()))
        ->toThrow(AuthorizationException::class);
});

test('the badge counts only the people who have submitted and are not yet confirmed', function () {
    ['head' => $head, 'employee' => $employee, 'cycle' => $cycle, 'assessment' => $assessment] = underConfirmingHead();
    Employee::factory()->create(); // somebody in another section entirely

    $counter = app(CountLdnaConfirmationsDue::class);

    // Nothing is owed on somebody who has not answered yet.
    expect($counter->confirmees($head, $cycle)->pluck('employee_id')->all())->toBe([$employee->id])
        ->and($counter->handle($head))->toBe(0);

    selfSubmit($assessment, $employee);

    expect(app(CountLdnaConfirmationsDue::class)->handle($head))->toBe(1);

    app(ConfirmLdnaAssessment::class)->handle($head, $assessment->fresh(), confirm: true);

    expect(app(CountLdnaConfirmationsDue::class)->handle($head))->toBe(0);

    $this->actingAs($head);
    $this->get(route('dashboard'))->assertOk()->assertSee('LDNA confirmations');
    Livewire::test('pages::ldna.confirmations')->assertSee($employee->listing_name);
});

test('the review page shows what they said only once it is submitted', function () {
    ['head' => $head, 'employee' => $employee, 'assessment' => $assessment] = underConfirmingHead();
    $this->actingAs($head);

    app(SaveSelfRating::class)->handle($employee->user, $assessment, levelsAllAt($assessment, 'superior'));

    Livewire::test('pages::ldna.review', ['assessment' => $assessment])->assertDontSee('They said: Superior');

    selfSubmit($assessment->fresh(), $employee, 'superior');

    Livewire::test('pages::ldna.review', ['assessment' => $assessment->fresh()])->assertSee('They said: Superior');
});

test('a head confirms from the review page', function () {
    ['head' => $head, 'employee' => $employee, 'assessment' => $assessment] = underConfirmingHead();

    selfSubmit($assessment, $employee);

    $this->actingAs($head);

    Livewire::test('pages::ldna.review', ['assessment' => $assessment->fresh()])
        ->call('confirm')
        ->assertHasNoErrors()
        ->assertRedirect(route('ldna.confirmations'));

    expect($assessment->fresh()->isConfirmed())->toBeTrue();
});

test('hr progress offers review where hr is the one to confirm', function () {
    $this->actingAs(User::factory()->hr()->create());
    $loner = Employee::factory()->create();
    $cycle = openLdna();

    Livewire::test('pages::ldna.show', ['cycle' => $cycle])
        ->assertSee(route('ldna.review', assessmentOf($loner, $cycle)));
});
