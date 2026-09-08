<?php

use App\Enums\LdType;
use App\Models\BudgetCap;
use App\Models\LdiTraining;
use App\Models\User;
use Livewire\Livewire;

test('a cap knows what the plans already committed against it', function () {
    $cap = BudgetCap::factory()->create([
        'year' => 2026,
        'budget_source' => 'Human Resource',
        'amount' => 300000,
    ]);

    LdiTraining::factory()->create([
        'budget_source' => 'Human Resource',
        'budget' => 54000,
        'date_start' => '2026-03-02',
        'date_end' => '2026-03-04',
    ]);
    LdiTraining::factory()->create([
        'budget_source' => 'Human Resource',
        'budget' => 10220,
        'date_start' => '2026-05-02',
        'date_end' => '2026-05-04',
    ]);

    expect($cap->committed())->toBe(64220.0)
        ->and($cap->remaining())->toBe(235780.0);
});

test('a plan from another year or another source does not count', function () {
    $cap = BudgetCap::factory()->create([
        'year' => 2026,
        'budget_source' => 'Human Resource',
        'amount' => 300000,
    ]);

    LdiTraining::factory()->create([
        'budget_source' => 'Human Resource',
        'budget' => 50000,
        'date_start' => '2025-03-02',
        'date_end' => '2025-03-04',
    ]);
    LdiTraining::factory()->create([
        'budget_source' => 'WFP-GAA 2026',
        'budget' => 50000,
        'date_start' => '2026-03-02',
        'date_end' => '2026-03-04',
    ]);

    expect($cap->committed())->toBe(0.0);
});

test('there is no cap when none was set for the source and year', function () {
    BudgetCap::factory()->create(['year' => 2026, 'budget_source' => 'Human Resource']);

    expect(BudgetCap::forSourceAndYear('Human Resource', 2026))->not->toBeNull()
        ->and(BudgetCap::forSourceAndYear('Human Resource', 2025))->toBeNull()
        ->and(BudgetCap::forSourceAndYear('WFP-GAA 2026', 2026))->toBeNull()
        ->and(BudgetCap::forSourceAndYear(null, 2026))->toBeNull();
});

test('hr can set a cap', function () {
    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::setup.budget-caps')
        ->call('create')
        ->set('year', 2026)
        ->set('budget_source', 'Human Resource')
        ->set('amount', 300000)
        ->call('save')
        ->assertHasNoErrors();

    expect((float) BudgetCap::first()->amount)->toBe(300000.0);
});

test('one source cannot have two caps in the same year', function () {
    $this->actingAs(User::factory()->hr()->create());

    BudgetCap::factory()->create(['year' => 2026, 'budget_source' => 'Human Resource']);

    Livewire::test('pages::setup.budget-caps')
        ->call('create')
        ->set('year', 2026)
        ->set('budget_source', 'Human Resource')
        ->set('amount', 100000)
        ->call('save')
        ->assertHasErrors('budget_source');
});

test('the same source can have its own cap in another year', function () {
    $this->actingAs(User::factory()->hr()->create());

    BudgetCap::factory()->create(['year' => 2026, 'budget_source' => 'Human Resource']);

    Livewire::test('pages::setup.budget-caps')
        ->call('create')
        ->set('year', 2027)
        ->set('budget_source', 'Human Resource')
        ->set('amount', 350000)
        ->call('save')
        ->assertHasNoErrors();

    expect(BudgetCap::count())->toBe(2);
});

test('an employee cannot open the budget caps screen', function () {
    $this->actingAs(User::factory()->employee()->create());

    $this->get(route('setup.budget-caps'))->assertForbidden();
});

test('the ldi form warns when a plan goes over the cap but still saves it', function () {
    $this->actingAs(User::factory()->hr()->create());

    BudgetCap::factory()->create([
        'year' => 2026,
        'budget_source' => 'Human Resource',
        'amount' => 50000,
    ]);

    $component = Livewire::test('pages::ldi.index')
        ->call('create')
        ->set('title', 'Over Budget Training')
        ->set('development_partner', 'Department of Health')
        ->set('facilitator', 'Drug Treatment and Rehabilitation Center Caraga')
        ->set('date_start', '2026-03-02')
        ->set('date_end', '2026-03-04')
        ->set('hours', 24)
        ->set('ld_type', LdType::Technical->value)
        ->set('budget_source', 'Human Resource')
        ->set('budget', 80000);

    expect($component->instance()->budgetHint)->toContain('Over the 2026 Human Resource cap by 30,000.00');

    $component->call('save')->assertHasNoErrors();

    expect(LdiTraining::where('title', 'Over Budget Training')->exists())->toBeTrue();
});

test('the ldi form shows what is left when the plan fits', function () {
    $this->actingAs(User::factory()->hr()->create());

    BudgetCap::factory()->create([
        'year' => 2026,
        'budget_source' => 'Human Resource',
        'amount' => 300000,
    ]);

    $component = Livewire::test('pages::ldi.index')
        ->call('create')
        ->set('date_start', '2026-03-02')
        ->set('budget_source', 'Human Resource')
        ->set('budget', 54000);

    expect($component->instance()->budgetHint)->toContain('246,000.00 left');
});

test('editing a plan does not count its own budget twice', function () {
    $this->actingAs(User::factory()->hr()->create());

    BudgetCap::factory()->create([
        'year' => 2026,
        'budget_source' => 'Human Resource',
        'amount' => 100000,
    ]);

    $plan = LdiTraining::factory()->create([
        'budget_source' => 'Human Resource',
        'budget' => 60000,
        'date_start' => '2026-03-02',
        'date_end' => '2026-03-04',
    ]);

    $component = Livewire::test('pages::ldi.index')->call('edit', $plan->id);

    expect($component->instance()->budgetHint)->toContain('40,000.00 left');
});
