<?php

use App\Models\User;

function wrongPassword(User $user, int $times): void
{
    foreach (range(1, $times) as $ignored) {
        test()->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
    }
}

test('five wrong passwords lock the account for fifteen minutes', function () {
    $user = User::factory()->create();

    wrongPassword($user, 5);

    // Even the right password is turned away now, and told for how long.
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertStatus(302)
        ->assertSessionHasErrors(['email' => 'Too many wrong passwords. Try again in 15 minutes.']);

    $this->assertGuest();

    // Fortify's own would have let them back in after one minute.
    $this->travel(14)->minutes();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();

    $this->travel(61)->seconds();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});

test('only wrong passwords count toward the lock', function () {
    $user = User::factory()->create();

    wrongPassword($user, 4);

    // A right one wipes the slate.
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->post(route('logout'));

    wrongPassword($user, 4);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});

test('one locked account does not lock anybody else out', function () {
    $locked = User::factory()->create();
    $colleague = User::factory()->create();

    wrongPassword($locked, 5);

    $this->post(route('login.store'), ['email' => $colleague->email, 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});
