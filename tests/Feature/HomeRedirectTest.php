<?php

use App\Models\User;

test('the front door sends a stranger to the log in page', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('the front door sends somebody signed in to their dashboard', function () {
    $this->actingAs(User::factory()->hr()->create());

    $this->get('/')->assertRedirect(route('dashboard'));
});

test('the log in page names the office and says where an account comes from', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('LDI System')
        ->assertSee('Drug Treatment and Rehabilitation Center Caraga')
        // Nobody registers here; HR makes the accounts.
        ->assertSee('Use the account HR set up for you.')
        ->assertSee('Email address')
        ->assertSee('Password');
});

test('every door into the system wears the same page', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('Drug Treatment and Rehabilitation Center Caraga');
});
