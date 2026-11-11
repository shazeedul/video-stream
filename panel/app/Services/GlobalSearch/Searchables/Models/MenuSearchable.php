<?php

declare(strict_types=1);

namespace App\Services\GlobalSearch\Searchables\Models;

use App\Services\GlobalSearch\Data\MenuItems;
use App\Services\GlobalSearch\Searchables\BaseSearchable;
use App\Services\GlobalSearch\SearchResult;
use Illuminate\Support\Collection;

final class MenuSearchable extends BaseSearchable
{
    public function __construct()
    {
        $this->category = 'Menu';
        $this->icon = 'menu';
        $this->priority = 1; // Higher priority for menu items
        $this->permissions = ['*']; // Usually accessible to all authenticated users
    }

    public function search(string $query, int $limit = 10): Collection
    {
        if (! $this->hasPermission()) {
            return collect();
        }

        return MenuItems::authorized()
            ->filter(fn (array $item): bool => $this->matchesQuery($item, $query))
            ->take($limit)
            ->map($this->formatMenuItem(...));
    }

    /**
     * Check if menu item matches the search query.
     */
    private function matchesQuery(array $item, string $query): bool
    {
        return (mb_stripos($item['title'] ?? '', $query) !== false) ||
            (mb_stripos(__($item['title']) ?? '', $query) !== false) ||
            (mb_stripos($item['category'] ?? '', $query) !== false) ||
            (mb_stripos(__($item['category']) ?? '', $query) !== false);
    }

    /**
     * Format a menu item into a search result.
     */
    private function formatMenuItem(array $item): SearchResult
    {
        $url = isset($item['url']) ? route($item['url']) : '#';
        $subtitle = __('Navigation').' - '.(__($item['category']) ?? __('Menu'));

        return $this->createResult(
            $this->generateId(),
            __($item['title'] ?? 'Unknown'),
            $subtitle,
            $url,
            'MenuItem'
        );
    }
}
