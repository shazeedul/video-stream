<?php

declare(strict_types=1);

use App\Actions\Role\UpdateRoleAction;
use App\Models\Permission;
use App\Models\Role;

it('updates role name and display name', function () {
    $action = app(UpdateRoleAction::class);
    $role = Role::create([
        'name' => 'old-name',
        'display_name' => 'Old Name',
        'guard_name' => 'web',
        'is_default' => false,
    ]);

    $updatedRole = $action->execute($role, [
        'name' => 'new-name',
        'display_name' => 'New Name',
    ]);

    expect($updatedRole->name)->toBe('new-name');
    expect($updatedRole->display_name)->toBe('New Name');
});

it('syncs role permissions', function () {
    $action = app(UpdateRoleAction::class);
    $role = Role::create([
        'name' => 'perm-sync-role',
        'display_name' => 'Permission Sync Role',
        'guard_name' => 'web',
        'is_default' => false,
    ]);

    $permissions = Permission::take(2)->get();
    $role->givePermissionTo($permissions);

    expect($role->permissions)->toHaveCount(2);

    $newPermissions = Permission::take(3)->skip(2)->pluck('id')->toArray();

    $action->execute($role, [
        'name' => $role->name,
        'display_name' => $role->display_name,
        'permissions' => $newPermissions,
    ]);

    expect($role->fresh()->permissions)->toHaveCount(3);
});

it('removes all permissions when empty array', function () {
    $action = app(UpdateRoleAction::class);
    $role = Role::create([
        'name' => 'remove-perms-role',
        'display_name' => 'Remove Perms Role',
        'guard_name' => 'web',
        'is_default' => false,
    ]);

    $permissions = Permission::take(2)->get();
    $role->givePermissionTo($permissions);

    expect($role->permissions)->toHaveCount(2);

    $action->execute($role, [
        'name' => $role->name,
        'display_name' => $role->display_name,
        'permissions' => [],
    ]);

    expect($role->fresh()->permissions)->toHaveCount(0);
});
