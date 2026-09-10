<?php

use App\Models\User;

test('the front door sends a stranger to the log in page', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('the front door sends somebody signed in to their dashboard', function () {
    $this->actingAs(User::factory()->hr()->create());

    $this->get('/')->assertRedirect(route('dashboard'));
});

test('the log in page says what the system is and where an account comes from', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('HR Training System')
        ->assertSee('Multi-level approval, from submission to endorsement')
        // Nobody registers here; HR makes the accounts.
        ->assertSee('Use the account HR set up for you.')
        ->assertSee('Authorized users only.')
        ->assertSee('Email address')
        ->assertSee('Password');
});

test('every door into the system wears the same page', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('HR Training System')
        ->assertSee('Authorized users only.');
});
