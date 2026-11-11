<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GlobalSearch\GlobalSearchManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GlobalSearchController extends Controller
{
    /**
     * Search across all registered searchables.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('query');
        $limit = (int) $request->input('limit', 10);

        $result = GlobalSearchManager::search($query, $limit);

        return new JsonResponse($result);
    }

    /**
     * Get available categories.
     */
    public function categories(): JsonResponse
    {
        $categories = GlobalSearchManager::getAvailableCategories();

        return new JsonResponse($categories);
    }

    /**
     * Search within a specific category.
     */
    public function searchInCategory(Request $request, string $category): JsonResponse
    {
        $query = $request->input('query');
        $limit = (int) $request->input('limit', 10);

        $result = GlobalSearchManager::searchInCategory($query, $category, $limit);

        return new JsonResponse($result);
    }
}
