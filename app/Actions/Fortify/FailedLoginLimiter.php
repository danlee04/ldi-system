<?php

namespace App\Actions\Fortify;

use Illuminate\Http\Request;
use Laravel\Fortify\LoginRateLimiter;

/**
 * Five wrong passwords lock that email, from that address, for fifteen
 * minutes — Fortify's own limiter forgives them after one.
 *
 * Only failures count: Fortify hits this on a wrong password and clears
 * it on a right one, so signing in and out through the day never trips
 * it. That is why config/fortify.php leaves the 'login' limiter null — a
 * named limiter counts every attempt, good ones included, and answers
 * with a bare 429 page instead of a message on the form.
 */
class FailedLoginLimiter extends LoginRateLimiter
{
    public const int LOCKOUT_SECONDS = 15 * 60;

    public function increment(Request $request): void
    {
        $this->limiter->hit($this->throttleKey($request), self::LOCKOUT_SECONDS);
    }
}
