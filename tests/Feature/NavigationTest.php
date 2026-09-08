<?php

use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;

test('an admin is not offered trainings of their own', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('My trainings')
        ->assertSee('Approvals');
});

test('a plain employee is offered their own trainings but nothing to approve', function () {
    $user = User::factory()->employee()->create();
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('My trainings')
        ->assertDontSee('Approvals');
});

test('an account with no employee record is not offered trainings of their own', function () {
    $this->actingAs(User::factory()->employee()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('My trainings');
});

test('a designated section head is offered approvals even when their role was never updated', function () {
    $user = User::factory()->employee()->create();
    $section = Section::factory()->create();
    $head = Employee::factory()->for($section)->create(['user_id' => $user->id]);
    $section->update(['section_head_employee_id' => $head->id]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Approvals');
});

test('a designated division head is offered approvals', function () {
    $user = User::factory()->employee()->create();
    $division = Division::factory()->create();
    $head = Employee::factory()->for(Section::factory()->for($division))->create(['user_id' => $user->id]);
    $division->update(['division_head_employee_id' => $head->id]);

    $this->actingAs($user);

    $this->get(route('dashboard'))->assertOk()->assertSee('Approvals');
});

test('hr is offered both, plus the setup section', function () {
    $user = User::factory()->hr()->create();
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('My trainings')
        ->assertSee('Approvals')
        ->assertSee('LDI trainings')
        ->assertSee('Budget caps');
});

test('a plain employee is not offered the organization section', function () {
    $user = User::factory()->employee()->create();
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Employees')
        ->assertDontSee('LDI trainings')
        ->assertDontSee('Budget caps');
});
