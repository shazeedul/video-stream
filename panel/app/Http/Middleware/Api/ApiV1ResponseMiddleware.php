<?php

declare(strict_types=1);

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApiV1ResponseMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Set API version header for all v1 API responses
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $response->header('API-Version', 'v1');
            $response->header('Content-Type', 'application/json');
        }

        return $response;
    }
}
