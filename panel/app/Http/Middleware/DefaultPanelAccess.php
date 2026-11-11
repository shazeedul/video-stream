<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class DefaultPanelAccess
{
    /**
     * Handle an incoming request.
     *
     * Ensure authenticated users can access the default portal panel.
     * Optionally redirect admin users to admin panel if they try to access default panel directly.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user, 401, 'Unauthenticated.');

        if ($user->can('admin.panel.view')) {
            return to_route('admin.dashboard');
        }

        return $next($request);
    }
}
