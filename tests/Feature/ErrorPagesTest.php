<?php

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;

test('each error status gets its own page in the sign-in design', function (int $status, string $title) {
    // As in production. With debug on, a status that has no page falls
    // through to the debug screen, which quotes this file and its strings.
    config(['app.debug' => false]);
    Route::get('/_error', fn () => abort($status));

    $this->get('/_error')
        ->assertStatus($status)
        ->assertSee($title)
        ->assertSee('HR Training System')
        ->assertSee('images/logo.png');
})->with([
    'not signed in' => [401, 'Sign in to continue'],
    'forbidden' => [403, "You don't have access"],
    'not found' => [404, 'Page not found'],
    'expired page' => [419, 'This page expired'],
    'too many requests' => [429, 'Too many attempts'],
    'server error' => [500, 'Something went wrong'],
    'maintenance' => [503, 'Down for maintenance'],
    'any other client error' => [405, "That request didn't work"],
    'any other server error' => [502, 'Something went wrong'],
]);

test('a 403 gives the reason the code wrote for people', function () {
    Route::get('/_error', fn () => abort(403, 'Your account is not linked to an employee record.'));

    $this->get('/_error')
        ->assertForbidden()
        ->assertSee("You don't have access")
        ->assertSee('Your account is not linked to an employee record.');
});

test('a refusal with no reason of its own gets the plain explanation', function () {
    Route::get('/_error', fn () => throw new AuthorizationException);

    $this->get('/_error')
        ->assertForbidden()
        ->assertSee("Your account doesn't have permission to open this.")
        ->assertDontSee('This action is unauthorized.');
});

test('a missing record never names the model behind it', function () {
    Route::get('/_error', fn () => User::query()->findOrFail(999));

    $this->get('/_error')
        ->assertNotFound()
        ->assertSee('Page not found')
        ->assertDontSee('No query results');
});

test('an unexpected failure shows the 500 page without its details', function () {
    Exceptions::fake();
    config(['app.debug' => false]);
    Route::get('/_error', fn () => throw new RuntimeException('SQLSTATE[HY000] [2002] Connection refused'));

    $this->get('/_error')
        ->assertInternalServerError()
        ->assertSee('Something went wrong')
        ->assertDontSee('SQLSTATE');

    Exceptions::assertReported(RuntimeException::class);
});

test('a 429 says how long to wait', function () {
    Route::get('/_error', fn () => throw new ThrottleRequestsException(headers: ['Retry-After' => 120]));

    $this->get('/_error')
        ->assertTooManyRequests()
        ->assertSee('2 minutes');
});

test('an expired page sends people to sign in again', function () {
    Route::get('/_error', fn () => abort(419));

    $this->get('/_error')
        ->assertStatus(419)
        ->assertSee('Sign in again')
        ->assertSee('href="'.route('login').'"', false);
});

test('the maintenance page offers the same address again', function () {
    Route::get('/_error', fn () => abort(503));

    $this->get('/_error')
        ->assertServiceUnavailable()
        ->assertSee('href="'.url('/_error').'"', false);
});

test('a maintenance page met inside a Livewire update offers the page behind it', function () {
    Route::post('/_error', fn () => abort(503));

    $this->post('/_error', [], ['X-Livewire' => '1', 'Referer' => url('/calendar')])
        ->assertServiceUnavailable()
        ->assertSee('href="'.url('/calendar').'"', false);
});
