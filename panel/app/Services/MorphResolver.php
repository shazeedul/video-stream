<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

final class MorphResolver
{
    /**
     * Resolve morph type alias to actual class name.
     *
     * @param  string  $type  The morph type alias or full class name
     * @return string The resolved full class name
     *
     * @throws InvalidArgumentException If type cannot be resolved
     */
    public function resolve(string $type): string
    {
        // First check Laravel's built-in morph map
        $morphMap = Relation::morphMap();
        if (isset($morphMap[$type])) {
            return $morphMap[$type];
        }

        // Check if it's already a full class name
        if (class_exists($type)) {
            return $type;
        }

        // Check custom registry from config
        $registry = $this->getRegistry();
        if (isset($registry[$type])) {
            return $registry[$type];
        }

        // Fallback strategy
        $this->handleFallback($type);

        // If we reach here with 'ignore' strategy, return original
        return $type;
    }

    /**
     * Get the reverse mapping (class name to alias).
     *
     * @param  string  $className  The full class name
     * @return string|null The morph alias or null if not found
     */
    public function reverseResolve(string $className): ?string
    {
        // Check Laravel's built-in morph map
        $morphMap = Relation::morphMap();
        $alias = array_search($className, $morphMap, true);
        if ($alias !== false) {
            return $alias;
        }

        // Check custom registry
        $registry = $this->getRegistry();
        $alias = array_search($className, $registry, true);

        return $alias !== false ? $alias : null;
    }

    /**
     * Check if a morph type exists in the registry.
     *
     * @param  string  $type  The morph type to check
     */
    public function exists(string $type): bool
    {
        $morphMap = Relation::morphMap();
        if (isset($morphMap[$type])) {
            return true;
        }

        if (class_exists($type)) {
            return true;
        }

        $registry = $this->getRegistry();

        return isset($registry[$type]);
    }

    /**
     * Get all registered morph types.
     */
    public function all(): array
    {
        return array_merge(
            Relation::morphMap(),
            $this->getRegistry()
        );
    }

    /**
     * Clear morph registry cache.
     */
    public function clearCache(): void
    {
        Cache::forget('morph_registry');
    }

    /**
     * Get morph registry from config with caching support.
     */
    private function getRegistry(): array
    {
        $cacheEnabled = config('morph_registry.cache_enabled', true);

        if (! $cacheEnabled) {
            return config('morph_registry.registry', []);
        }

        $cacheKey = 'morph_registry';
        $cacheTtl = config('morph_registry.cache_ttl', 3600);

        return Cache::remember($cacheKey, $cacheTtl, fn () => config('morph_registry.registry', []));
    }

    /**
     * Handle fallback strategy for unresolved types.
     *
     *
     * @throws InvalidArgumentException
     */
    private function handleFallback(string $type): void
    {
        $strategy = config('morph_registry.fallback_strategy', 'exception');

        switch ($strategy) {
            case 'log':
                logger()->warning('Cannot resolve morph type: '.$type);
                break;

            case 'ignore':
                // Do nothing
                break;

            case 'exception':
            default:
                throw new InvalidArgumentException('Cannot resolve morph type: '.$type);
        }
    }
}
