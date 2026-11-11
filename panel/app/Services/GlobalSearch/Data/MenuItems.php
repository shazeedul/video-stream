<?php

declare(strict_types=1);

namespace App\Services\GlobalSearch\Data;

use Illuminate\Support\Collection;

final class MenuItems
{
    /**
     * Get all menu items as a collection.
     */
    public static function all(): Collection
    {
        return collect(self::items());
    }

    /**
     * Get menu items by category.
     */
    public static function byCategory(string $category): Collection
    {
        return self::all()->where('category', $category);
    }

    /**
     * Search menu items by title.
     */
    public static function search(string $query): Collection
    {
        return self::all()->filter(fn ($item): bool => mb_stripos((string) $item['title'], $query) !== false);
    }

    /**
     * Get menu items the user has permission for.
     */
    public static function authorized(): Collection
    {
        return self::all()->filter(function (array $item): bool {
            $user = auth()->user();

            if (! $user) {
                return false;
            }

            foreach ($item['can'] as $permission) {
                if ($permission === '*' || $user->can($permission)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Get all categories.
     */
    public static function categories(): Collection
    {
        return self::all()->pluck('category')->unique()->sort()->values();
    }

    /**
     * Get raw menu items array.
     * Synced with resources/js/config/navigation.ts
     */
    private static function items(): array
    {
        return [
            // Main Navigation
            [
                'title' => 'Dashboard',
                'url' => 'admin.dashboard',
                'icon' => 'layout-grid',
                'category' => 'Navigation',
                'can' => ['*'],
            ],
            // System - Administration
            [
                'title' => 'Roles',
                'url' => 'admin.roles.index',
                'icon' => 'shield',
                'category' => 'System',
                'can' => ['roles.view'],
            ],
            [
                'title' => 'Users',
                'url' => 'admin.users.index',
                'icon' => 'users',
                'category' => 'System',
                'can' => ['users.view'],
            ],
            [
                'title' => 'Settings',
                'url' => 'admin.settings.index',
                'icon' => 'cog',
                'category' => 'System',
                'can' => ['system-settings.view'],
            ],
        ];
    }
}
