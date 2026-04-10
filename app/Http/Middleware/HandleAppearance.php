<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Default to 'dark' — Aesthetic AI uses a luxury dark theme.
        // Users can switch to 'light' via Clinic Settings.
        View::share('appearance', $request->cookie('appearance') ?? 'dark');

        return $next($request);
    }
}
