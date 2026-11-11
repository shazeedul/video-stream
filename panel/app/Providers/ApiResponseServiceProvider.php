<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

final class ApiResponseServiceProvider extends ServiceProvider
{
    /**
     * Register API response macros.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap API response macros.
     */
    public function boot(): void
    {
        // Success macro - Consistent API v1 response format
        Response::macro('success', function ($data = null, ?string $message = null, int $code = 200, ...$additionalData) {
            $response = [
                'success' => true,
                'message' => $message ?? 'Request successful',
                'data' => $data,
                'code' => $code,
                'timestamp' => now()->toISOString(),
            ];

            // Merge additional data using foreach
            foreach ($additionalData as $key => $value) {
                if (is_array($value)) {
                    $response = array_merge($response, $value);
                } else {
                    $response[$key] = $value;
                }
            }

            return Response::json($response, $code);
        });

        // Error macro - Consistent API v1 response format
        Response::macro('error', function ($errors = null, ?string $message = null, int $code = 400, ...$additionalData) {
            $response = [
                'success' => false,
                'message' => $message ?? 'Request failed',
                'errors' => $errors,
                'code' => $code,
                'timestamp' => now()->toISOString(),
            ];

            // Merge additional data using foreach
            foreach ($additionalData as $key => $value) {
                if (is_array($value)) {
                    $response = array_merge($response, $value);
                } else {
                    $response[$key] = $value;
                }
            }

            return Response::json($response, $code);
        });
    }
}
