<?php

declare(strict_types=1);

use App\Actions\Role\DeleteRoleAction;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

it('deletes a role', function () {
    $action = app(DeleteRoleAction::class);
    $role = Role::create([
        'name' => 'deletable-role',
        'display_name' => 'Deletable Role',
        'guard_name' => 'web',
        'is_default' => false,
    ]);

    $result = $action->execute($role);

    expect($result)->toBeTrue();
    expect(Role::where('name', 'deletable-role')->exists())->toBeFalse();
});

it('removes permissions before deleting role', function () {
    $action = app(DeleteRoleAction::class);
    $role = Role::create([
        'name' => 'role-with-perms',
        'display_name' => 'Role With Perms',
        'guard_name' => 'web',
        'is_default' => false,
    ]);

    $permissions = Permission::take(2)->get();
    $role->givePermissionTo($permissions);

    $action->execute($role);

    expect(Role::where('name', 'role-with-perms')->exists())->toBeFalse();
});

it('throws exception when deleting default role', function () {
    $action = app(DeleteRoleAction::class);
    $role = Role::where('is_default', true)->first();

    $action->execute($role);
})->throws(Exception::class, 'Cannot delete default role');

it('throws exception when role has users assigned', function () {
    $action = app(DeleteRoleAction::class);
    $role = Role::create([
        'name' => 'role-with-users',
        'display_name' => 'Role With Users',
        'guard_name' => 'web',
        'is_default' => false,
    ]);

    $user = User::factory()->create();
    $user->assignRole($role);

    $action->execute($role);
})->throws(Exception::class, "Cannot delete role 'Role With Users' because it is assigned to 1 user(s)");
