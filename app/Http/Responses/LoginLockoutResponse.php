<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LockoutResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Says how long in minutes, not seconds: "try again in 847 seconds" asks
 * the reader to do arithmetic at the worst moment.
 */
class LoginLockoutResponse implements LockoutResponse
{
    public function __construct(private readonly LoginRateLimiter $limiter) {}

    /**
     * @param  Request  $request
     *
     * @throws ValidationException
     */
    public function toResponse($request): Response
    {
        $minutes = (int) max(1, ceil($this->limiter->availableIn($request) / 60));

        throw ValidationException::withMessages([
            Fortify::username() => [
                trans_choice(
                    'Too many wrong passwords. Try again in :minutes minute.|Too many wrong passwords. Try again in :minutes minutes.',
                    $minutes,
                    ['minutes' => $minutes],
                ),
            ],
        ])->status(429);
    }
}
