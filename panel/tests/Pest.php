<?php

declare(strict_types=1);

use App\Services\RolePermissionSyncService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
 * |--------------------------------------------------------------------------
 * | Test Case
 * |--------------------------------------------------------------------------
 * |
 * | The closure you provide to your test functions is always bound to a specific PHPUnit test
 * | case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
 * | need to change it using the "pest()" function to bind a different classes or traits.
 * |
 */

pest()
    ->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Ensure roles & permissions are seeded for all feature tests
beforeEach(function (): void {
    // Clear permission cache first
    app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

    // Seed additional roles & permissions
    $aclSyncService = app(RolePermissionSyncService::class);
    $seeder = new RolePermissionSeeder($aclSyncService);
    $seeder->run();

    $seeder = new UserSeeder();
    $seeder->run();

    // Reset cached roles and permissions to ensure fresh state for each test
    app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

/*
 * |--------------------------------------------------------------------------
 * | Expectations
 * |--------------------------------------------------------------------------
 * |
 * | When you're writing tests, you often need to check that values meet certain conditions. The
 * | "expect()" function gives you access to a set of "expectations" methods that you can use
 * | to assert different things. Of course, you may extend the Expectation API at any time.
 * |
 */

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
 * |--------------------------------------------------------------------------
 * | Functions
 * |--------------------------------------------------------------------------
 * |
 * | While Pest is very powerful out-of-the-box, you may have some testing code specific to your
 * | project that you don't want to repeat in every file. Here you can also expose helpers as
 * | global functions to help you to reduce the number of lines of code in your test files.
 * |
 */

function actingAs($user)
{
    return test()->actingAs($user);
}

function getJson($uri)
{
    return test()->getJson($uri);
}

function postJson($uri, array $data = [])
{
    return test()->postJson($uri, $data);
}

function adminUser(): App\Models\User
{
    $user = App\Models\User::factory()->admin()->create();
    $user->assignRole('admin');

    return $user;
}

function portalUser(): App\Models\User
{
    $user = App\Models\User::factory()->create();
    $user->assignRole('user');

    return $user;
}

function userWithPermissions(array $permissions): App\Models\User
{
    $user = App\Models\User::factory()->create();

    foreach ($permissions as $permission) {
        $user->givePermissionTo($permission);
    }

    return $user;
}

function userWithRole(string $roleName): App\Models\User
{
    $user = App\Models\User::factory()->create();
    $user->assignRole($roleName);

    return $user;
}
