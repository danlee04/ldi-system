<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nobody sees another person's records until they have agreed, once, to
 * keep them confidential. guest() remembers where they were going.
 */
class EnsureConfidentialityAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->confidentiality_accepted_at === null) {
            return redirect()->guest(route('confidentiality'));
        }

        return $next($request);
    }
}
