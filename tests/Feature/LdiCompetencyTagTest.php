<?php

use App\Enums\LdType;
use App\Models\Competency;
use App\Models\LdiTraining;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->hr()->create()));

test('hr tags a plan with the competencies it addresses', function () {
    $addressed = Competency::factory()->technical()->create();
    Competency::factory()->technical()->create();

    Livewire::test('pages::ldi.index')
        ->call('create')
        ->set('title', 'Crisis intervention training')
        ->set('development_partner', 'DOH')
        ->set('facilitator', 'DOH')
        ->set('date_start', '2027-03-01')
        ->set('date_end', '2027-03-03')
        ->set('hours', 24)
        ->set('ld_type', LdType::Technical->value)
        ->set('competencyIds', [(string) $addressed->id])
        ->call('save')
        ->assertHasNoErrors();

    expect(LdiTraining::where('title', 'Crisis intervention training')->first()->competencies->pluck('id')->all())
        ->toBe([$addressed->id]);
});

test('editing loads the tags, and untagging removes one', function () {
    $plan = LdiTraining::factory()->create();
    $kept = Competency::factory()->core()->create();
    $dropped = Competency::factory()->core()->create();
    $plan->competencies()->attach([$kept->id, $dropped->id]);

    Livewire::test('pages::ldi.index')
        ->call('edit', $plan->id)
        ->assertSet('competencyIds', [(string) $kept->id, (string) $dropped->id])
        ->set('competencyIds', [(string) $kept->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($plan->competencies()->pluck('competencies.id')->all())->toBe([$kept->id]);
});

test('a deactivated competency already on a plan survives an edit', function () {
    $plan = LdiTraining::factory()->create();
    $retired = Competency::factory()->technical()->inactive()->create();
    $plan->competencies()->attach($retired->id);

    $component = Livewire::test('pages::ldi.index')->call('edit', $plan->id);

    expect($component->instance()->competencyChoices->pluck('id'))->toContain($retired->id);

    $component->call('save')->assertHasNoErrors();

    expect($plan->competencies()->pluck('competencies.id')->all())->toBe([$retired->id]);
});

test('a failure tagging the plan rolls back the plan too', function () {
    // Drops the pivot table so competencies()->sync() fails after the plan
    // itself would otherwise have been saved, proving both happen in one
    // transaction rather than leaving a plan with stale or missing tags.
    Schema::drop('competency_ldi_training');

    expect(fn () => Livewire::test('pages::ldi.index')
        ->call('create')
        ->set('title', 'Crisis intervention training')
        ->set('development_partner', 'DOH')
        ->set('facilitator', 'DOH')
        ->set('date_start', '2027-03-01')
        ->set('date_end', '2027-03-03')
        ->set('hours', 24)
        ->set('ld_type', LdType::Technical->value)
        ->call('save'))
        ->toThrow(QueryException::class);

    expect(LdiTraining::where('title', 'Crisis intervention training')->exists())->toBeFalse();
});

test('a tag must name a competency that exists', function () {
    $plan = LdiTraining::factory()->create();

    Livewire::test('pages::ldi.index')
        ->call('edit', $plan->id)
        ->set('competencyIds', ['999999'])
        ->call('save')
        ->assertHasErrors('competencyIds.0');
});
