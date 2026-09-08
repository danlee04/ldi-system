<?php

use App\Enums\ApprovalLevel;
use App\Models\Employee;
use App\Models\Section;
use App\Models\TrainingRecord;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard counts my own records by state', function () {
    $user = User::factory()->employee()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    TrainingRecord::factory()->for($employee)->count(2)->create();
    TrainingRecord::factory()->for($employee)->approved()->create();
    TrainingRecord::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSet('myPending', 2)
        ->assertSet('myApproved', 1);
});

test('the dashboard tells a head how many records await them', function () {
    $user = User::factory()->sectionHead()->create();
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')->assertSet('awaitingMe', 0);
});

test('a head sees a record that is actually waiting on them', function () {
    $section = Section::factory()->create();

    $headUser = User::factory()->sectionHead()->create();
    $head = Employee::factory()->for($section)->create(['user_id' => $headUser->id]);
    $section->update(['section_head_employee_id' => $head->id]);

    $employee = Employee::factory()->for($section)->create();
    TrainingRecord::factory()->for($employee)->create([
        'current_level' => ApprovalLevel::SectionHead,
    ]);

    $this->actingAs($headUser);

    Livewire::test('pages::dashboard')->assertSet('awaitingMe', 1);
});

test('hr is warned about records nobody can approve', function () {
    $section = Section::factory()->create(['section_head_employee_id' => null]);
    $employee = Employee::factory()->for($section)->create();

    TrainingRecord::factory()->for($employee)->create(['current_level' => null]);

    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::dashboard')
        ->assertSet('unroutable', 1)
        ->assertSee('cannot move because no head is designated');
});

test('an ordinary employee is not shown the unroutable warning', function () {
    $section = Section::factory()->create(['section_head_employee_id' => null]);
    $employee = Employee::factory()->for($section)->create();

    TrainingRecord::factory()->for($employee)->create(['current_level' => null]);

    $user = User::factory()->employee()->create();
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')->assertSet('unroutable', 0);
});
