<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tells somebody signed out for being idle why they are looking at the
 * login form, rather than leaving them to wonder whether it crashed.
 *
 * The flash lands in the fresh session Fortify starts after logging out.
 */
class LogoutResponse implements LogoutResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        if ($request->input('reason') === 'idle') {
            return redirect()->route('login')->with('status', __('You were signed out after :minutes minutes without activity.', [
                'minutes' => config('session.idle_timeout'),
            ]));
        }

        return redirect()->route('home');
    }
}
