<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminPanelAccess
{
    /**
     * Handle an incoming request.
     *
     * Restrict access to admin panel based on 'admin.panel.view' permission.
     * If user doesn't have permission, redirect to user panel.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user, 401, 'Unauthenticated.');

        // If user doesn't have admin panel access, redirect to user panel
        if (! $user->can('admin.panel.view')) {
            return to_route('portal.dashboard');
        }

        return $next($request);
    }
}
