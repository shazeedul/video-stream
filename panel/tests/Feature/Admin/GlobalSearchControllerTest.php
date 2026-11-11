<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\GlobalSearch\GlobalSearchManager;

use function Pest\Laravel\actingAs;

/*
 * |--------------------------------------------------------------------------
 * | Global Search Tests
 * |--------------------------------------------------------------------------
 */

it('returns available search categories for admin', function () {
    $admin = adminUser();

    actingAs($admin)
        ->getJson(route('admin.search.categories'))
        ->assertOk();
});

it('denies search access for unauthorized users', function () {
    $user = portalUser();

    actingAs($user)
        ->getJson(route('admin.search.index', ['q' => 'test']))
        // Portal users are redirected by admin.panel middleware
        ->assertRedirect();
});

it('searches across all categories', function () {
    $admin = adminUser();

    // Create a user to search for
    User::factory()->create(['name' => 'Searchable User']);

    actingAs($admin)
        ->getJson(route('admin.search.index', ['query' => 'Searchable', 'limit' => 5]))
        ->assertOk()
        ->assertJsonStructure(['results', 'categories']);
});

it('searches with custom limit', function () {
    $admin = adminUser();

    actingAs($admin)
        ->getJson(route('admin.search.index', ['query' => 'test', 'limit' => 20]))
        ->assertOk();
});

it('searches within a specific category', function () {
    $admin = adminUser();

    actingAs($admin)
        ->getJson(route('admin.search.category', ['category' => 'users', 'query' => 'test', 'limit' => 10]))
        ->assertOk();
});

/*
 * |--------------------------------------------------------------------------
 * | GlobalSearchManager Unit Tests
 * |--------------------------------------------------------------------------
 */

it('clears search cache', function () {
    GlobalSearchManager::clearCache();
    $searchables = GlobalSearchManager::getSearchables();

    expect($searchables)->toBeInstanceOf(Illuminate\Support\Collection::class);
});

it('returns empty results for whitespace query', function () {
    $results = GlobalSearchManager::search('   ', 10);

    expect($results['results'])->toBeEmpty();
    expect($results['categories'])->toBeEmpty();
});

it('searches within a specific category using manager', function () {
    $results = GlobalSearchManager::searchInCategory('test', 'users', 10);

    expect($results)->toBeArray();
    expect($results)->toHaveKey('results');
});
