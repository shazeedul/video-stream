<?php

declare(strict_types=1);

namespace App\Services\GlobalSearch\Searchables\Models;

use App\Models\User;
use App\Services\GlobalSearch\Searchables\ModelSearchable;
use App\Services\GlobalSearch\SearchResult;
use Illuminate\Database\Eloquent\Model;

final class UserSearchable extends ModelSearchable
{
    protected string $modelClass = User::class;

    protected array $searchableFields = ['name', 'email'];

    protected string $category = 'Users';

    protected string $icon = 'user';

    protected array $permissions = ['users.view'];

    protected int $priority = 10;

    protected function formatModel(Model $model): SearchResult
    {
        /** @var User $model */
        $subtitle = $model->email;

        return $this->createResult(
            (string) $model->id,
            $model->name,
            $subtitle,
            route('admin.users.index', [
                'search' => $model->email,
            ]),
            'User'
        );
    }
}
