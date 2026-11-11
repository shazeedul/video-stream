<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckUserStatus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Allow if no user or user is not suspended
        if (! $user || $user->status !== UserStatus::SUSPENDED) {
            return $next($request);
        }

        // Redirect to suspended page if not already there or logging out
        if (! $request->routeIs('suspended') && ! $request->routeIs('logout')) {
            return to_route('suspended');
        }

        return $next($request);
    }
}
