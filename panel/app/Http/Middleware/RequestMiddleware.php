<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class RequestMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):((Response|RedirectResponse))  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next, $method)
    {
        if ($method === 'ajax' && ! $request->ajax()) {
            abort(403, 'Only Ajax Request Allow');
        } elseif ($method === 'http' && $request->ajax()) {
            abort(403, 'Only Non-Ajax Request Allow');
        }

        return $next($request);
    }
}
