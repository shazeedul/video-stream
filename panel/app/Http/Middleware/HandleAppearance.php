<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

final class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appearance = 'system';

        $appearance = Auth::check() ? Auth::user()->getPreference('appearance', $appearance) : $request->cookie('appearance', $appearance);

        View::share('appearance', $appearance);

        $response = $next($request);

        // Queue the cookie instead of using withCookie to support all response types
        cookie()->queue('appearance', $appearance, 525600, '/', null, null, false);

        return $response;
    }
}
