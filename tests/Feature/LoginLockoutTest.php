<?php

use App\Models\User;

/**
 * The whole opening tag that carries the marker, whatever order Flux
 * writes its attributes in.
 */
function openingTag(string $html, string $marker): string
{
    // Quoted values read whole: x-bind:disabled="left > 0" holds a ">".
    preg_match_all('/<(?:input|button)\b(?:"[^"]*"|[^">])*>/s', $html, $tags);

    return collect($tags[0])->first(fn (string $tag): bool => str_contains($tag, $marker)) ?? '';
}

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

test('a locked account finds the password and the button shut, with the time left', function () {
    $user = User::factory()->create();

    wrongPassword($user, 5);

    $html = $this->from(route('login'))
        ->followingRedirects()
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSee('You can try again in')
        ->assertSee('left: 900', false)
        ->getContent();

    expect(openingTag($html, 'name="password"'))->toContain('disabled="disabled"')
        ->and(openingTag($html, 'data-test="login-button"'))->toContain('disabled="disabled"');
});

test('the login form opens as usual when nobody is locked out', function () {
    $html = $this->get(route('login'))->assertSee('left: 0', false)->getContent();

    expect(openingTag($html, 'name="password"'))->not->toContain('disabled="disabled"');
});
