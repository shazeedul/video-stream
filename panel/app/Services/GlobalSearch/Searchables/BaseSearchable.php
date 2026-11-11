<?php

declare(strict_types=1);

namespace App\Services\GlobalSearch\Searchables;

use App\Services\GlobalSearch\Contracts\SearchableInterface;
use App\Services\GlobalSearch\SearchResult;
use Illuminate\Support\Str;

abstract class BaseSearchable implements SearchableInterface
{
    protected string $category;

    protected string $icon;

    protected array $permissions = [];

    protected int $priority = 100;

    final public function getCategory(): string
    {
        return $this->category;
    }

    final public function getIcon(): string
    {
        return $this->icon;
    }

    final public function hasPermission(): bool
    {
        if ($this->permissions === []) {
            return true;
        }

        $user = auth()->user();

        if (! $user) {
            return false;
        }

        foreach ($this->permissions as $permission) {
            if ($permission === '*' || $user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    final public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Create a search result.
     */
    protected function createResult(
        string $id,
        string $title,
        string $subtitle,
        string $url,
        ?string $modelType = null
    ): SearchResult {
        return SearchResult::make(
            $id,
            $title,
            $subtitle,
            $url,
            $this->getCategory(),
            $this->getIcon(),
            $modelType
        );
    }

    /**
     * Generate a random ID for non-model results.
     */
    protected function generateId(): string
    {
        return Str::random(10);
    }
}
