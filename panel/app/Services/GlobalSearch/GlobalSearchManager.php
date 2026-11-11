<?php

declare(strict_types=1);

namespace App\Services\GlobalSearch;

use App\Services\GlobalSearch\Contracts\SearchableInterface;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use ReflectionClass;

final class GlobalSearchManager
{
    private static ?Collection $searchables = null;

    /**
     * Get all searchables (auto-discovered).
     */
    public static function getSearchables(): Collection
    {
        if (! self::$searchables instanceof Collection) {
            self::$searchables = self::discoverSearchables();
        }

        return self::$searchables;
    }

    /**
     * Search across all searchables.
     */
    public static function search(string $query, int $limit = 10): array
    {
        if (in_array(mb_trim($query), ['', '0'], true)) {
            return [
                'results' => [],
                'categories' => [],
            ];
        }

        $searchables = self::getSearchables()->filter(fn ($searchable) => $searchable->hasPermission());
        $allResults = collect();
        $categoryStats = [];

        foreach ($searchables as $searchable) {
            $results = $searchable->search($query, $limit);

            if ($results->isNotEmpty()) {
                $allResults = $allResults->concat($results);

                $categoryName = $searchable->getCategory();
                if (! isset($categoryStats[$categoryName])) {
                    $categoryStats[$categoryName] = [
                        'name' => $categoryName,
                        'icon' => $searchable->getIcon(),
                        'count' => 0,
                    ];
                }

                $categoryStats[$categoryName]['count'] += $results->count();
            }
        }

        // Sort results by relevance
        $formattedResults = $allResults
            ->sortBy(fn ($result): int => mb_stripos((string) $result->getTitle(), $query) === 0 ? 0 : 1)
            ->take($limit)
            ->map(fn ($result) => $result->toArray())
            ->values();

        return [
            'results' => $formattedResults,
            'categories' => array_values($categoryStats),
        ];
    }

    /**
     * Search within a specific category.
     */
    public static function searchInCategory(string $query, string $category, int $limit = 10): array
    {
        if (in_array(mb_trim($query), ['', '0'], true)) {
            return [
                'results' => [],
                'categories' => [],
            ];
        }

        $searchables = self::getSearchables()
            ->filter(fn ($searchable) => $searchable->hasPermission())
            ->filter(fn ($searchable): bool => $searchable->getCategory() === $category);

        $allResults = collect();
        $categoryStats = [];

        foreach ($searchables as $searchable) {
            $results = $searchable->search($query, $limit);

            if ($results->isNotEmpty()) {
                $allResults = $allResults->concat($results);

                $categoryName = $searchable->getCategory();
                if (! isset($categoryStats[$categoryName])) {
                    $categoryStats[$categoryName] = [
                        'name' => $categoryName,
                        'icon' => $searchable->getIcon(),
                        'count' => 0,
                    ];
                }

                $categoryStats[$categoryName]['count'] += $results->count();
            }
        }

        $formattedResults = $allResults
            ->take($limit)
            ->map(fn ($result) => $result->toArray())
            ->values();

        return [
            'results' => $formattedResults,
            'categories' => array_values($categoryStats),
        ];
    }

    /**
     * Get available categories.
     */
    public static function getAvailableCategories(): Collection
    {
        return self::getSearchables()
            ->filter(fn ($searchable) => $searchable->hasPermission())
            ->groupBy(fn ($searchable) => $searchable->getCategory())
            ->map(function ($searchables, $categoryName): array {
                $firstSearchable = $searchables->first();

                return [
                    'name' => $categoryName,
                    'icon' => $firstSearchable->getIcon(),
                    'count' => $searchables->count(),
                ];
            })
            ->values();
    }

    /**
     * Clear cached searchables (useful for testing).
     */
    public static function clearCache(): void
    {
        self::$searchables = null;
    }

    /**
     * Auto-discover all searchable classes.
     */
    private static function discoverSearchables(): Collection
    {
        $searchables = collect();

        // Auto-discover all searchables in Models directory
        $searchables = $searchables->concat(self::discoverModelSearchables());

        return $searchables->sortBy(fn ($searchable) => $searchable->getPriority());
    }

    /**
     * Auto-discover model searchable classes.
     */
    private static function discoverModelSearchables(): Collection
    {
        $searchables = collect();
        $searchablePath = app_path('Services/GlobalSearch/Searchables/Models');

        if (! File::exists($searchablePath)) {
            return $searchables;
        }

        $files = File::files($searchablePath);

        foreach ($files as $file) {
            $className = 'App\\Services\\GlobalSearch\\Searchables\\Models\\'.$file->getFilenameWithoutExtension();

            if (class_exists($className)) {
                $reflection = new ReflectionClass($className);

                if (! $reflection->isAbstract() &&
                    $reflection->implementsInterface(SearchableInterface::class)) {
                    try {
                        $searchables->push(new $className());
                    } catch (Exception) {
                        // Skip classes that can't be instantiated
                        continue;
                    }
                }
            }
        }

        return $searchables;
    }
}
