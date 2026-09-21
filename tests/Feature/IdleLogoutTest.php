<?php

use App\Models\User;

test('every signed-in page carries the idle sign-out, set to twenty minutes', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('idleMs: 20 * 60000', false)
        ->assertSee('<input type="hidden" name="reason" value="idle">', false)
        ->assertSee('Still there?');
});

test('an idle sign-out lands on the login form saying why', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('logout'), ['reason' => 'idle'])
        ->assertRedirect(route('login'));

    $this->assertGuest();

    $this->get(route('login'))
        ->assertSee('You were signed out after 20 minutes without activity.');
});

test('an ordinary log out says nothing about being idle', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('logout'))->assertRedirect(route('home'));

    $this->get(route('login'))->assertDontSee('without activity');
});

test('the login form no longer offers to remember the browser', function () {
    // A remembered browser would sign itself back in past the idle limit.
    $this->get(route('login'))->assertDontSee('Remember me');
});
