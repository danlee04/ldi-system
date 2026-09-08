<?php

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('only an admin can open the accounts screen', function () {
    $this->actingAs(User::factory()->hr()->create());

    $this->get(route('setup.users'))->assertForbidden();
});

test('an admin can create an account for an employee', function () {
    $this->actingAs(User::factory()->admin()->create());

    $employee = Employee::factory()->create(['user_id' => null]);

    Livewire::test('pages::setup.users')
        ->call('create')
        ->set('employeeId', $employee->id)
        ->set('name', 'Maria Cruz')
        ->set('email', 'maria@example.test')
        ->set('role', UserRole::Employee->value)
        ->set('password', 'a-strong-password')
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('email', 'maria@example.test')->first();

    expect($user)->not->toBeNull()
        ->and($employee->fresh()->user_id)->toBe($user->id)
        ->and(Hash::check('a-strong-password', $user->password))->toBeTrue();
});

test('an admin can change somebody role', function () {
    $this->actingAs(User::factory()->admin()->create());

    $user = User::factory()->employee()->create();

    Livewire::test('pages::setup.users')
        ->call('edit', $user->id)
        ->set('role', UserRole::Hr->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->role)->toBe(UserRole::Hr);
});

test('leaving the password empty keeps the current one', function () {
    $this->actingAs(User::factory()->admin()->create());

    $user = User::factory()->employee()->create();
    $before = $user->password;

    Livewire::test('pages::setup.users')
        ->call('edit', $user->id)
        ->set('name', 'Renamed Person')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->password)->toBe($before)
        ->and($user->fresh()->name)->toBe('Renamed Person');
});

test('an admin can reset a password', function () {
    $this->actingAs(User::factory()->admin()->create());

    $user = User::factory()->employee()->create();

    Livewire::test('pages::setup.users')
        ->call('edit', $user->id)
        ->set('password', 'a-brand-new-password')
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check('a-brand-new-password', $user->fresh()->password))->toBeTrue();
});

test('an email cannot collide with another account', function () {
    $this->actingAs(User::factory()->admin()->create());

    User::factory()->create(['email' => 'taken@example.test']);
    $user = User::factory()->create(['email' => 'mine@example.test']);

    Livewire::test('pages::setup.users')
        ->call('edit', $user->id)
        ->set('email', 'taken@example.test')
        ->call('save')
        ->assertHasErrors('email');
});

test('moving an account to another employee releases the first one', function () {
    $this->actingAs(User::factory()->admin()->create());

    $user = User::factory()->employee()->create();
    $first = Employee::factory()->create(['user_id' => $user->id]);
    $second = Employee::factory()->create(['user_id' => null]);

    Livewire::test('pages::setup.users')
        ->call('edit', $user->id)
        ->set('employeeId', $second->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($first->fresh()->user_id)->toBeNull()
        ->and($second->fresh()->user_id)->toBe($user->id);
});

test('a deactivated account cannot sign in', function () {
    $user = User::factory()->create([
        'email' => 'blocked@example.test',
        'password' => Hash::make('their-password'),
        'is_active' => false,
    ]);

    $this->post(route('login'), [
        'email' => 'blocked@example.test',
        'password' => 'their-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('an active account still signs in', function () {
    User::factory()->create([
        'email' => 'allowed@example.test',
        'password' => Hash::make('their-password'),
        'is_active' => true,
    ]);

    $this->post(route('login'), [
        'email' => 'allowed@example.test',
        'password' => 'their-password',
    ]);

    $this->assertAuthenticated();
});
