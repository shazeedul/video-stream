<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use App\Services\GlobalSearch\Data\MenuItems;
use App\Services\GlobalSearch\GlobalSearchManager;
use App\Services\GlobalSearch\Searchables\BaseSearchable;
use App\Services\GlobalSearch\Searchables\ModelSearchable;
use App\Services\GlobalSearch\SearchResult;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;

it('returns menu items and categories', function () {
    $categories = MenuItems::categories();
    expect($categories)->toContain('Navigation');
    expect($categories)->toContain('System');

    $matches = MenuItems::search('Role');
    expect($matches)->not->toBeEmpty();
});

it('filters authorized menu items', function () {
    auth()->logout();
    expect(MenuItems::authorized())->toBeEmpty();

    $role = Role::factory()->create();
    $permission = Permission::firstOrCreate(['name' => 'roles.view', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $user = User::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user);

    $authorized = MenuItems::authorized();
    expect($authorized)->not->toBeEmpty();
});

it('handles base searchable permissions', function () {
    $searchable = new class extends BaseSearchable
    {
        protected string $category = 'Demo';

        protected string $icon = 'icon';

        protected array $permissions = [];

        public function search(string $query, int $limit = 10): Illuminate\Support\Collection
        {
            return collect();
        }
    };

    expect($searchable->hasPermission())->toBeTrue();
});

it('builds results for model searchables', function () {
    $searchable = new class extends ModelSearchable
    {
        protected string $modelClass = User::class;

        protected array $searchableFields = ['name'];

        protected string $category = 'Users';

        protected string $icon = 'user';

        protected function formatModel(Model $model): SearchResult
        {
            return $this->createResult((string) $model->id, $model->name, $model->email ?? '', '/users', 'User');
        }
    };

    $user = User::factory()->create(['name' => 'Alice']);

    $results = $searchable->search('Ali', 10);

    expect($results)->toHaveCount(1);
    expect($results->first()->getTitle())->toBe('Alice');
});

it('converts search result to array', function () {
    $result = SearchResult::make('1', 'Title', 'Subtitle', '/url', 'Category', 'icon', 'Model');

    expect($result->toArray())->toMatchArray([
        'id' => '1',
        'title' => 'Title',
        'subtitle' => 'Subtitle',
        'url' => '/url',
        'category' => 'Category',
        'icon' => 'icon',
        'model_type' => 'Model',
    ]);
});

it('searches across available categories', function () {
    GlobalSearchManager::clearCache();

    $role = Role::factory()->create();
    $permission = Permission::firstOrCreate(['name' => 'users.view', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $user = User::factory()->create(['name' => 'Tom', 'email' => 'tom@example.com']);
    $user->assignRole($role);
    $this->actingAs($user);

    $results = GlobalSearchManager::search('Tom', 5);
    expect($results['results'])->not->toBeEmpty();

    $categories = GlobalSearchManager::getAvailableCategories();
    expect($categories)->not->toBeEmpty();
});

it('returns menu search results when query matches', function () {
    GlobalSearchManager::clearCache();

    $user = User::factory()->create();
    $this->actingAs($user);

    $results = GlobalSearchManager::search('Dashboard', 5);
    expect($results['results'])->not->toBeEmpty();
});

it('returns empty results for blank query', function () {
    $results = GlobalSearchManager::search('  ');

    expect($results['results'])->toBe([]);
    expect($results['categories'])->toBe([]);
});

it('searches within a category', function () {
    GlobalSearchManager::clearCache();

    $role = Role::factory()->create();
    $permission = Permission::firstOrCreate(['name' => 'users.view', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $user = User::factory()->create(['name' => 'Sarah', 'email' => 'sarah@example.com']);
    $user->assignRole($role);
    $this->actingAs($user);

    $results = GlobalSearchManager::searchInCategory('Sarah', 'Users');
    expect($results['results'])->not->toBeEmpty();
});
