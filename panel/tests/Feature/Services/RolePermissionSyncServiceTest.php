<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Services\RolePermissionSyncService;

it('returns role definitions', function () {
    $service = app(RolePermissionSyncService::class);
    $definitions = $service->getAllRoleDefinitions();

    expect($definitions)->toBeArray();
    expect($definitions)->not->toBeEmpty();
});

it('returns permission definitions', function () {
    $service = app(RolePermissionSyncService::class);
    $definitions = $service->getAllPermissionDefinitions();

    expect($definitions)->toBeArray();
    expect($definitions)->not->toBeEmpty();
});

it('returns flattened permissions', function () {
    $service = app(RolePermissionSyncService::class);
    $permissions = $service->getFlattenedPermissions();

    expect($permissions)->toBeInstanceOf(Illuminate\Support\Collection::class);
    expect($permissions)->not->toBeEmpty();
});

it('syncs roles and permissions', function () {
    $service = app(RolePermissionSyncService::class);

    $result = $service->sync();

    expect($result)->toHaveKey('stats');
    expect($result)->toHaveKey('logs');
});

it('returns stats after sync', function () {
    $service = app(RolePermissionSyncService::class);
    $service->sync();

    $stats = $service->getStats();

    expect($stats)->toBeArray();
});

it('returns logs after sync', function () {
    $service = app(RolePermissionSyncService::class);
    $service->sync();

    $logs = $service->getLogs();

    expect($logs)->toBeArray();
});

it('can run in dry run mode', function () {
    $service = app(RolePermissionSyncService::class);
    $service->dryRun(true);

    $result = $service->sync();

    expect($result)->toHaveKey('stats');
});

it('syncs role assignments for default roles', function () {
    $adminRole = Role::firstOrCreate(['name' => 'admin'], ['is_default' => true, 'guard_name' => 'web']);
    $userRole = Role::firstOrCreate(['name' => 'user'], ['is_default' => true, 'guard_name' => 'web']);

    $permission = Permission::firstOrCreate([
        'name' => 'profile.view',
        'guard_name' => 'web',
    ], [
        'display_name' => 'View User Profile',
        'group' => 'Settings & Profile',
    ]);

    $adminRole->givePermissionTo($permission);

    $service = app(RolePermissionSyncService::class);
    $service->sync();

    expect($userRole->hasPermissionTo($permission->name))->toBeTrue();
});

it('removes extra role assignments for default roles', function () {
    $adminRole = Role::firstOrCreate(['name' => 'admin'], ['is_default' => true, 'guard_name' => 'web']);
    $userRole = Role::firstOrCreate(['name' => 'user'], ['is_default' => true, 'guard_name' => 'web']);

    $permission = Permission::firstOrCreate([
        'name' => 'roles.view',
        'guard_name' => 'web',
    ], [
        'display_name' => 'View Roles',
        'group' => 'Roles',
    ]);

    $adminRole->givePermissionTo($permission);
    $userRole->givePermissionTo($permission);

    $service = app(RolePermissionSyncService::class);
    $service->sync();

    expect($userRole->hasPermissionTo($permission->name))->toBeFalse();
});

it('deletes orphaned permissions', function () {
    $orphan = Permission::query()->create([
        'name' => 'orphaned.permission',
        'display_name' => 'Orphaned',
        'group' => 'Orphaned',
        'guard_name' => 'web',
    ]);

    $service = app(RolePermissionSyncService::class);
    $service->sync();

    expect(Permission::query()->where('name', $orphan->name)->exists())->toBeFalse();
});
