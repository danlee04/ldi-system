<?php

use App\Enums\LdType;
use App\Models\Employee;
use App\Models\LdiTraining;
use App\Models\TrainingRecord;
use App\Models\User;
use Livewire\Livewire;

test('an employee cannot open the ldi screens', function () {
    $this->actingAs(User::factory()->employee()->create());

    $this->get(route('ldi.index'))->assertForbidden();
});

test('hr can create a plan with its budget', function () {
    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::ldi.index')
        ->call('create')
        ->set('title', 'Self-Defense and Restraint Training')
        ->set('development_partner', 'Department of Health')
        ->set('type_of_training', 'Training')
        ->set('date_start', '2026-03-02')
        ->set('date_end', '2026-03-04')
        ->set('hours', 24)
        ->set('ld_type', LdType::Technical->value)
        ->set('target_attendees', 18)
        ->set('budget', 54000)
        ->set('budget_source', 'WFP-GAA 2026')
        ->call('save')
        ->assertHasNoErrors();

    $plan = LdiTraining::first();

    expect($plan->title)->toBe('Self-Defense and Restraint Training')
        ->and($plan->target_attendees)->toBe(18)
        ->and((float) $plan->budget)->toBe(54000.0);
});

test('hr adds attendees and the plan counts them', function () {
    $hr = User::factory()->hr()->create();
    $this->actingAs($hr);

    $plan = LdiTraining::factory()->create(['target_attendees' => 3]);
    $employees = Employee::factory()->count(2)->create();

    Livewire::test('pages::ldi.show', ['plan' => $plan])
        ->call('openAttendees')
        ->set('selected', $employees->pluck('id')->all())
        ->call('addAttendees');

    expect($plan->trainingRecords()->count())->toBe(2);
});

test('the page adds up what the attendees cost', function () {
    $hr = User::factory()->hr()->create();
    $this->actingAs($hr);

    $plan = LdiTraining::factory()->create(['budget' => 10000]);
    $employee = Employee::factory()->create();

    $component = Livewire::test('pages::ldi.show', ['plan' => $plan])
        ->call('openAttendees')
        ->set('selected', [$employee->id])
        ->call('addAttendees');

    $record = $plan->trainingRecords()->first();

    $component->call('editCost', $record->id)
        ->set('registration_fee', 1500)
        ->set('tev', 2000)
        ->set('expenses', 500)
        ->call('saveCost')
        ->assertHasNoErrors();

    expect($component->instance()->actualSpend)->toBe(4000.0);
});

test('an attendee can be removed from the plan', function () {
    $hr = User::factory()->hr()->create();
    $this->actingAs($hr);

    $plan = LdiTraining::factory()->create();
    $employee = Employee::factory()->create();

    $component = Livewire::test('pages::ldi.show', ['plan' => $plan])
        ->call('openAttendees')
        ->set('selected', [$employee->id])
        ->call('addAttendees');

    $record = $plan->trainingRecords()->first();

    $component->call('confirmRemove', $record->id)
        ->assertSet('removingId', $record->id)
        ->call('removeAttendee');

    expect($plan->trainingRecords()->count())->toBe(0)
        ->and(TrainingRecord::count())->toBe(0);
});

test('somebody already attending is not offered again', function () {
    $hr = User::factory()->hr()->create();
    $this->actingAs($hr);

    $plan = LdiTraining::factory()->create();
    $attending = Employee::factory()->create(['last_name' => 'Bonifacio']);
    Employee::factory()->create(['last_name' => 'Jacinto']);

    $component = Livewire::test('pages::ldi.show', ['plan' => $plan])
        ->call('openAttendees')
        ->set('selected', [$attending->id])
        ->call('addAttendees');

    $candidates = $component->instance()->candidates->pluck('last_name');

    expect($candidates)->not->toContain('Bonifacio')
        ->and($candidates)->toContain('Jacinto');
});

test('the year filter narrows the plan list', function () {
    $this->actingAs(User::factory()->hr()->create());

    LdiTraining::factory()->create(['title' => 'This Year Plan', 'date_start' => '2026-03-02', 'date_end' => '2026-03-04']);
    LdiTraining::factory()->create(['title' => 'Last Year Plan', 'date_start' => '2025-03-02', 'date_end' => '2025-03-04']);

    Livewire::test('pages::ldi.index')
        ->set('filterYear', 2026)
        ->assertSee('This Year Plan')
        ->assertDontSee('Last Year Plan');
});

test('a division head cannot add attendees', function () {
    $this->actingAs(User::factory()->divisionHead()->create());

    $plan = LdiTraining::factory()->create();

    Livewire::test('pages::ldi.show', ['plan' => $plan])->assertForbidden();
});
