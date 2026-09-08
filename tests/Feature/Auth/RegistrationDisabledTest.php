<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

test('public registration is disabled', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse();
});

test('the registration route does not exist', function () {
    expect(Route::has('register'))->toBeFalse();
});
