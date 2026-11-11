<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SystemSetting;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class SystemSettingService
{
    /**
     * Configuration settings.
     */
    private array $config;

    /**
     * Whether caching is enabled.
     */
    private readonly bool $isCachingEnabled;

    /**
     * Cache lifetime in minutes.
     */
    private readonly int $cacheLifetime;

    public function __construct()
    {
        $this->config = config('system-setting', []);
        $this->isCachingEnabled = $this->config['cache']['enabled'] ?? true;
        $this->cacheLifetime = $this->config['cache']['lifetime'] ?? 60 * 24 * 365;
    }

    /**
     * Retrieve all settings with optional sorting.
     */
    public function all(string $order = 'asc'): Collection
    {
        $order = mb_strtolower($order) === 'desc' ? 'desc' : 'asc';
        $cacheKey = $this->getCacheKey($this->config['cache']['keys']['all_'.$order]);

        if ($this->isCachingEnabled) {
            return Cache::remember($cacheKey, $this->cacheLifetime, fn () => SystemSetting::query()
                ->sortOrder($order)
                ->get());
        }

        return SystemSetting::query()->sortOrder($order)->get();
    }

    /**
     * Retrieve a specific setting by its key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();
        $setting = $settings->where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Set a specific setting by its key.
     */
    public function set(string $key, mixed $value): bool
    {
        $setting = SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        $this->flush();

        return $setting->exists;
    }

    /**
     * Retrieve distinct setting groups with optional sorting.
     */
    public function onlyGroups(string $order = 'asc'): Collection
    {
        $order = mb_strtolower($order) === 'desc' ? 'desc' : 'asc';
        $cacheKey = $this->getCacheKey($this->config['cache']['keys']['only_groups_'.$order]);

        if ($this->isCachingEnabled) {
            return Cache::remember($cacheKey, $this->cacheLifetime, fn () => SystemSetting::query()
                ->select('group')
                ->visible()
                ->distinct()
                ->orderBy('group', $order)
                ->pluck('group'));
        }

        return SystemSetting::query()
            ->select('group')
            ->visible()
            ->distinct()
            ->orderBy('group', $order)
            ->pluck('group');
    }

    /**
     * Group settings by their group field with optional sorting.
     */
    public function groups(string $order = 'asc'): Collection
    {
        $order = mb_strtolower($order) === 'desc' ? 'desc' : 'asc';
        $cacheKey = $this->getCacheKey($this->config['cache']['keys']['groups_'.$order]);

        if ($this->isCachingEnabled) {
            return Cache::remember($cacheKey, $this->cacheLifetime, function () use ($order): Collection {
                $settings = $this->all($order);
                $groupData = [];

                foreach ($settings as $setting) {
                    $groupData[$setting->group][] = $setting;
                }

                return collect($groupData);
            });
        }

        $settings = $this->all($order);
        $groupData = [];

        foreach ($settings as $setting) {
            $groupData[$setting->group][] = $setting;
        }

        return collect($groupData);
    }

    /**
     * Retrieve settings for a specific group with optional sorting.
     */
    public function group(string $group, string $order = 'asc'): Collection
    {
        $order = mb_strtolower($order) === 'desc' ? 'desc' : 'asc';

        return $this->all($order)->where('group', $group);
    }

    /**
     * Get only public and non-encrypted settings for frontend exposure.
     */
    public function publicSettings(): Collection
    {
        $cacheKey = $this->getCacheKey($this->config['cache']['keys']['public']);

        if ($this->isCachingEnabled) {
            return Cache::remember($cacheKey, $this->cacheLifetime, fn () => SystemSetting::query()
                ->where('is_public', true)
                ->where('is_encrypted', false)
                ->where('is_visible', true)
                ->sortOrder()
                ->get()
                ->mapWithKeys(fn (SystemSetting $setting): array => [
                    $setting->key => [
                        'value' => $setting->value,
                        'url' => $this->getFileUrl($setting),
                    ],
                ]));
        }

        return SystemSetting::query()
            ->where('is_public', true)
            ->where('is_encrypted', false)
            ->where('is_visible', true)
            ->sortOrder()
            ->get()
            ->mapWithKeys(fn (SystemSetting $setting): array => [
                $setting->key => [
                    'value' => $setting->value,
                    'url' => $this->getFileUrl($setting),
                ],
            ]);
    }

    /**
     * Clear all cached settings data.
     */
    public function flush(): void
    {
        if (! $this->isCachingEnabled) {
            return;
        }

        // Flush predefined cache keys
        foreach ($this->config['cache']['keys'] ?? [] as $key) {
            Cache::forget($this->getCacheKey($key));
        }

        // Flush all cache keys with the system_settings prefix using tags or pattern
        $prefix = $this->config['cache']['prefix'] ?? 'system_settings';

        // Get all cache keys with the prefix and clear them
        // This ensures group-specific caches are also cleared
        try {
            // For Redis or Memcached with tag support
            Cache::tags([$prefix])->flush();
        } catch (Exception) {
            // Fallback: manually clear known pattern keys
            // This works for file/database cache drivers that don't support tags
            $groups = SystemSetting::query()->distinct()->pluck('group');
            foreach ($groups as $group) {
                Cache::forget($this->getCacheKey(sprintf('group_%s_asc', $group)));
                Cache::forget($this->getCacheKey(sprintf('group_%s_desc', $group)));
            }
        }
    }

    /**
     * Get the file URL for file/image type settings.
     */
    private function getFileUrl(SystemSetting $setting): ?string
    {
        // Only return URL for file and image types
        if (! in_array($setting->type, [\App\Enums\SystemSettingType::FILE, \App\Enums\SystemSettingType::IMAGE])) {
            return null;
        }

        // Don't return URL for empty values
        if (! $setting->value) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::url($setting->value);
    }

    /**
     * Get the cache key for a specific query type.
     */
    private function getCacheKey(string $key): string
    {
        $prefix = $this->config['cache']['prefix'] ?? 'system_settings';

        return $prefix.':'.$key;
    }
}
