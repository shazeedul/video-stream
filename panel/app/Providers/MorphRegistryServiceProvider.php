<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\MorphResolver;
use Exception;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class MorphRegistryServiceProvider extends ServiceProvider
{
    /**
     * Clear morph registry cache.
     */
    public static function clearCache(): void
    {
        Cache::forget('morph_registry');
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        // Register MorphResolver as singleton
        $this->app->singleton('morph.resolver', fn ($app): MorphResolver => new MorphResolver());
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerMorphMappings();
    }

    /**
     * Register polymorphic type registry.
     */
    private function registerMorphMappings(): void
    {
        $registry = $this->getMorphRegistry();

        if ($registry === []) {
            return;
        }

        Relation::morphMap($registry);
    }

    /**
     * Get morph registry from config with caching support.
     */
    private function getMorphRegistry(): array
    {
        $cacheEnabled = config('morph_registry.cache_enabled', true);

        if (! $cacheEnabled) {
            return $this->loadRegistryFromConfig();
        }

        try {
            $cacheKey = 'morph_registry';
            $cacheTtl = config('morph_registry.cache_ttl', 3600);

            return Cache::remember($cacheKey, $cacheTtl, $this->loadRegistryFromConfig(...));
        } catch (Exception) {
            return $this->loadRegistryFromConfig();
        }
    }

    /**
     * Load registry from config file.
     */
    private function loadRegistryFromConfig(): array
    {
        $registry = config('morph_registry.registry', []);

        if (empty($registry)) {
            $this->handleFallback('No morph registry found in config file.');

            return [];
        }

        // Validate registry
        $validatedRegistry = $this->validateRegistry($registry);

        return $validatedRegistry;
    }

    /**
     * Validate that all registered classes exist.
     */
    private function validateRegistry(array $registry): array
    {
        $validRegistry = [];
        $invalidRegistry = [];

        foreach ($registry as $alias => $class) {
            if (class_exists($class)) {
                $validRegistry[$alias] = $class;
            } else {
                $invalidRegistry[] = sprintf("Class '%s' not found for alias '%s'", $class, $alias);
            }
        }

        if ($invalidRegistry !== []) {
            $this->handleFallback('Invalid morph registry: '.implode(', ', $invalidRegistry));
        }

        return $validRegistry;
    }

    /**
     * Handle fallback strategy for errors.
     */
    private function handleFallback(string $message): void
    {
        $strategy = config('morph_registry.fallback_strategy', 'exception');

        switch ($strategy) {
            case 'log':
                Log::warning('MorphRegistryServiceProvider: '.$message);
                break;

            case 'ignore':
                // Do nothing
                break;

            case 'exception':
            default:
                throw new InvalidArgumentException('MorphRegistryServiceProvider: '.$message);
        }
    }
}
