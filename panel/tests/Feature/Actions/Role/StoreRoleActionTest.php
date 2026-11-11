<?php

declare(strict_types=1);

use App\Actions\Role\StoreRoleAction;
use App\Models\Permission;
use App\Models\Role;

use function Pest\Laravel\assertDatabaseHas;

it('creates a role with basic data', function () {
    $action = app(StoreRoleAction::class);
    $permission = Permission::first();

    $role = $action->execute([
        'name' => 'test-role',
        'display_name' => 'Test Role',
        'description' => 'A test role',
        'permissions' => [$permission->id],
    ]);

    expect($role)->toBeInstanceOf(Role::class);
    expect($role->name)->toBe('test-role');
    expect($role->display_name)->toBe('Test Role');
    expect($role->is_default)->toBeFalse();

    assertDatabaseHas('roles', [
        'name' => 'test-role',
        'display_name' => 'Test Role',
    ]);
});

it('creates a role without permissions', function () {
    $action = app(StoreRoleAction::class);

    $role = $action->execute([
        'name' => 'no-perm-role',
        'display_name' => 'No Permission Role',
    ]);

    expect($role)->toBeInstanceOf(Role::class);
    expect($role->permissions)->toHaveCount(0);
});

it('creates a role with multiple permissions', function () {
    $action = app(StoreRoleAction::class);
    $permissions = Permission::take(3)->pluck('id')->toArray();

    $role = $action->execute([
        'name' => 'multi-perm-role',
        'display_name' => 'Multi Permission Role',
        'permissions' => $permissions,
    ]);

    expect($role->permissions)->toHaveCount(3);
});

it('sets is_default to false for new roles', function () {
    $action = app(StoreRoleAction::class);

    $role = $action->execute([
        'name' => 'new-custom-role',
        'display_name' => 'New Custom Role',
    ]);

    expect($role->is_default)->toBeFalse();
});
