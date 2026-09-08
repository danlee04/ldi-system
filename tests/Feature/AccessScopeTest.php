<?php

use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;

test('hr sees every employee', function () {
    Employee::factory()->count(3)->create();

    expect(Employee::visibleTo(User::factory()->hr()->create())->count())->toBe(3);
});

test('a division head sees only their own division', function () {
    $division = Division::factory()->create();
    $ownSection = Section::factory()->for($division)->create();
    Employee::factory()->count(2)->for($ownSection)->create();
    Employee::factory()->for(Section::factory()->create())->create();

    $head = User::factory()->divisionHead()->create();
    Employee::factory()->for($ownSection)->create(['user_id' => $head->id]);

    expect(Employee::visibleTo($head)->count())->toBe(3);
});

test('a section head sees only their own section', function () {
    $division = Division::factory()->create();
    $ownSection = Section::factory()->for($division)->create();
    $siblingSection = Section::factory()->for($division)->create();

    Employee::factory()->for($ownSection)->create();
    Employee::factory()->for($siblingSection)->create();

    $head = User::factory()->sectionHead()->create();
    Employee::factory()->for($ownSection)->create(['user_id' => $head->id]);

    expect(Employee::visibleTo($head)->count())->toBe(2);
});

test('a plain employee sees only themselves', function () {
    Employee::factory()->count(3)->create();

    $user = User::factory()->employee()->create();
    Employee::factory()->create(['user_id' => $user->id]);

    expect(Employee::visibleTo($user)->count())->toBe(1);
});

test('a user with no employee record sees nothing', function () {
    Employee::factory()->count(2)->create();

    expect(Employee::visibleTo(User::factory()->sectionHead()->create())->count())->toBe(0);
});

test('only admin and hr may edit employees', function () {
    $employee = Employee::factory()->create();

    expect(User::factory()->hr()->create()->can('update', $employee))->toBeTrue()
        ->and(User::factory()->admin()->create()->can('update', $employee))->toBeTrue()
        ->and(User::factory()->divisionHead()->create()->can('update', $employee))->toBeFalse();
});
