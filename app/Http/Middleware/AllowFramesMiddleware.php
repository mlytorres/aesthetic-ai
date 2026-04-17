<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Specifically allows framing for intake pages so they can be embedded in clinic websites.
 * Removes X-Frame-Options: DENY and sets a relaxed Content-Security-Policy.
 */
class AllowFramesMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Remove the restrictive X-Frame-Options header
        $response->headers->remove('X-Frame-Options');
        
        // Explicitly set frame-ancestors to allow any domain if 'embedded=true' is present,
        // or just generally for the intake route.
        // In production, we should ideally restrict this to the clinic's domain.
        $csp = "frame-ancestors *;";
        $response->headers->set('Content-Security-Policy', $csp, false);

        return $response;
    }
}
