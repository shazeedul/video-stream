<?php

declare(strict_types=1);

namespace App\Services\GlobalSearch\Searchables;

use App\Services\GlobalSearch\SearchResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

abstract class ModelSearchable extends BaseSearchable
{
    protected string $modelClass;

    protected array $searchableFields = [];

    public function __construct()
    {
        throw_if(! isset($this->modelClass) || ($this->modelClass === '' || $this->modelClass === '0'), InvalidArgumentException::class, 'Model class must be defined');

        throw_if($this->searchableFields === [], InvalidArgumentException::class, 'Searchable fields must be defined');
    }

    /**
     * Format a model into a search result.
     */
    abstract protected function formatModel(Model $model): SearchResult;

    final public function search(string $query, int $limit = 10): Collection
    {
        if (! $this->hasPermission()) {
            return collect();
        }

        $queryBuilder = $this->buildQuery($query);
        $results = $queryBuilder->limit($limit)->get();

        return $results->map($this->formatModel(...));
    }

    /**
     * Build the query for searching models.
     */
    protected function buildQuery(string $query): Builder
    {
        $queryBuilder = $this->modelClass::query();

        // Add search conditions
        $queryBuilder->where(function ($q) use ($query): void {
            foreach ($this->searchableFields as $index => $field) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $q->$method($field, 'like', sprintf('%%%s%%', $query));
            }
        });

        // Apply custom query modifications
        $this->modifyQuery($queryBuilder, $query);

        return $queryBuilder;
    }

    /**
     * Override this method to modify the query (e.g., add joins, filters, etc.).
     */
    protected function modifyQuery(Builder $query, string $searchQuery): void
    {
        // Override in child classes if needed
    }
}
