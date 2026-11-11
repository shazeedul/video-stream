<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class BaseApiController extends Controller
{
    /**
     * API version for this controller.
     */
    protected string $version = 'v1';

    /**
     * Return success response.
     */
    protected function successResponse($data = null, ?string $message = null, int $code = 200, array $meta = []): JsonResponse
    {
        return response()->success($data, $message, $code, $meta);
    }

    /**
     * Return error response.
     */
    protected function errorResponse($errors = null, ?string $message = null, int $code = 400, array $meta = []): JsonResponse
    {
        return response()->error($errors, $message, $code, $meta);
    }

    /**
     * Return paginated response.
     */
    protected function paginatedResponse($paginator, ?string $message = null, array $meta = []): JsonResponse
    {
        $data = $paginator->items();

        $pagination = [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more_pages' => $paginator->hasMorePages(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];

        return $this->successResponse($data, $message, 200, array_merge($pagination, $meta));
    }

    /**
     * Return not found response.
     */
    protected function notFoundResponse(?string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse(null, $message, 404);
    }

    /**
     * Return validation error response.
     */
    protected function validationErrorResponse($errors, ?string $message = 'Validation failed'): JsonResponse
    {
        return $this->errorResponse($errors, $message, 422);
    }

    /**
     * Return unauthorized response.
     */
    protected function unauthorizedResponse(?string $message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse(null, $message, 401);
    }

    /**
     * Return forbidden response.
     */
    protected function forbiddenResponse(?string $message = 'Forbidden'): JsonResponse
    {
        return $this->errorResponse(null, $message, 403);
    }
}
