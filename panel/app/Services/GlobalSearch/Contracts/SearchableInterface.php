<?php

declare(strict_types=1);

namespace App\Services\GlobalSearch\Contracts;

use Illuminate\Support\Collection;

interface SearchableInterface
{
    /**
     * Get the category name for this searchable type.
     */
    public function getCategory(): string;

    /**
     * Get the icon for this searchable type.
     */
    public function getIcon(): string;

    /**
     * Check if the current user has permission to search this type.
     */
    public function hasPermission(): bool;

    /**
     * Search for items matching the given query.
     */
    public function search(string $query, int $limit = 10): Collection;

    /**
     * Get the priority for ordering search results (lower = higher priority).
     */
    public function getPriority(): int;
}
