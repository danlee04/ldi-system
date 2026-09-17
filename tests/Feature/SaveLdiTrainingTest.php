<?php

use App\Actions\Ldi\SaveLdiTraining;
use App\Enums\LdType;
use App\Models\Competency;
use App\Models\LdiTraining;
use App\Models\User;

/**
 * The attributes the page would have validated and handed to the action,
 * with sane defaults so each test only sets what it is about.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function ldiAttributes(array $overrides = []): array
{
    return [
        'title' => 'Records Management Seminar',
        'development_partner' => 'Civil Service Commission',
        'facilitator' => 'Civil Service Commission',
        'type_of_training' => '',
        'training_communication' => '',
        'cpd_units' => null,
        'date_start' => '2026-03-02',
        'date_end' => '2026-03-04',
        'hours' => 24,
        'ld_type' => LdType::Technical->value,
        'ld_type_other' => '',
        'location' => '',
        'target_attendees' => null,
        'budget_amount' => null,
        'other_budget_source' => '',
        'other_budget_amount' => null,
        ...$overrides,
    ];
}

test('hr is named as a fund only when hr put an amount in', function () {
    $hr = User::factory()->hr()->create();

    $funded = app(SaveLdiTraining::class)->handle($hr, null, ldiAttributes(['budget_amount' => 6000]));
    $unfunded = app(SaveLdiTraining::class)->handle($hr, null, ldiAttributes(['other_budget_source' => 'WFP-GAA 2026', 'other_budget_amount' => 54000]));

    expect($funded->budget_source)->toBe(LdiTraining::HR_SOURCE)
        ->and($unfunded->budget_source)->toBeNull();
});

test('the budget is the two funds added up, or null when both are empty', function () {
    $hr = User::factory()->hr()->create();

    $both = app(SaveLdiTraining::class)->handle($hr, null, ldiAttributes([
        'budget_amount' => 6000,
        'other_budget_source' => 'WFP-GAA 2026',
        'other_budget_amount' => 13000,
    ]));
    $neither = app(SaveLdiTraining::class)->handle($hr, null, ldiAttributes());

    expect((float) $both->budget)->toBe(19000.0)
        ->and($neither->budget)->toBeNull();
});

test('ld type other survives only for the other type', function () {
    $hr = User::factory()->hr()->create();

    $other = app(SaveLdiTraining::class)->handle($hr, null, ldiAttributes([
        'ld_type' => LdType::Other->value,
        'ld_type_other' => 'Bespoke workshop',
    ]));
    $technical = app(SaveLdiTraining::class)->handle($hr, null, ldiAttributes([
        'ld_type' => LdType::Technical->value,
        'ld_type_other' => 'Should not be kept',
    ]));

    expect($other->ld_type_other)->toBe('Bespoke workshop')
        ->and($technical->ld_type_other)->toBeNull();
});

test('competencies are synced and re-saving replaces them', function () {
    $hr = User::factory()->hr()->create();
    $first = Competency::factory()->technical()->create();
    $second = Competency::factory()->technical()->create();
    $third = Competency::factory()->technical()->create();

    $plan = app(SaveLdiTraining::class)->handle($hr, null, ldiAttributes(), [$first->id, $second->id]);

    expect($plan->competencies()->pluck('competencies.id')->sort()->values()->all())
        ->toBe([$first->id, $second->id]);

    $plan = app(SaveLdiTraining::class)->handle($hr, $plan, ldiAttributes(), [$second->id, $third->id]);

    expect($plan->competencies()->pluck('competencies.id')->sort()->values()->all())
        ->toBe([$second->id, $third->id]);
});

test('updating an existing plan keeps its id', function () {
    $hr = User::factory()->hr()->create();
    $plan = LdiTraining::factory()->create();

    $saved = app(SaveLdiTraining::class)->handle($hr, $plan, ldiAttributes(['title' => 'Updated Title']));

    expect($saved->id)->toBe($plan->id)
        ->and(LdiTraining::count())->toBe(1)
        ->and($saved->title)->toBe('Updated Title');
});

test('created by is recorded as the given user', function () {
    $hr = User::factory()->hr()->create();

    $plan = app(SaveLdiTraining::class)->handle($hr, null, ldiAttributes());

    expect($plan->created_by)->toBe($hr->id);
});
