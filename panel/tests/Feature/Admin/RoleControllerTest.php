<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\delete;

/*
 * |--------------------------------------------------------------------------
 * | Role Index Tests
 * |--------------------------------------------------------------------------
 */

it('displays roles index page for admin', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get(route('admin.roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/roles/index')
            ->has('roles')
            ->has('filters'));
});

it('denies access to roles index for unauthorized users', function () {
    $user = portalUser();

    actingAs($user)
        ->get(route('admin.roles.index'))
        // Portal users are redirected by admin.panel middleware
        ->assertRedirect();
});

it('filters roles by search query', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get(route('admin.roles.index', ['search' => 'admin']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.search', 'admin'));
});

it('filters roles by default status', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get(route('admin.roles.index', ['is_default' => '1']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.is_default', '1'));
});

it('sorts roles by permissions count', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get(route('admin.roles.index', ['sort' => 'permissions_count']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'permissions_count'));
});

/*
 * |--------------------------------------------------------------------------
 * | Role Create Tests
 * |--------------------------------------------------------------------------
 */

it('displays role create page for admin', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get(route('admin.roles.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/roles/create-edit')
            ->has('permissions')
            ->where('mode', 'create'));
});

it('denies access to role create for unauthorized users', function () {
    $user = portalUser();

    actingAs($user)
        ->get(route('admin.roles.create'))
        // Portal users are redirected by admin.panel middleware
        ->assertRedirect();
});

/*
 * |--------------------------------------------------------------------------
 * | Role Store Tests
 * |--------------------------------------------------------------------------
 */

it('creates a new role', function () {
    $admin = adminUser();
    $permission = Permission::first();

    $roleData = [
        'name' => 'test_role',
        'display_name' => 'Test Role',
        'description' => 'A test role',
        'permissions' => [$permission->id],
    ];

    actingAs($admin)
        ->post(route('admin.roles.store'), $roleData)
        ->assertRedirect(route('admin.roles.index'))
        ->assertSessionHas('success', 'Role created successfully.');

    assertDatabaseHas('roles', [
        'name' => 'test_role',
        'display_name' => 'Test Role',
    ]);
});

it('denies role creation without permission', function () {
    $user = portalUser();

    actingAs($user)
        ->post(route('admin.roles.store'), [
            'name' => 'test-role',
            'display_name' => 'Test Role',
        ])
        // Portal users are redirected by admin.panel middleware
        ->assertRedirect();
});

it('validates required fields when creating role', function () {
    $admin = adminUser();

    actingAs($admin)
        ->post(route('admin.roles.store'), [])
        ->assertSessionHasErrors(['name', 'display_name']);
});

/*
 * |--------------------------------------------------------------------------
 * | Role Show Tests
 * |--------------------------------------------------------------------------
 */

it('displays role details page', function () {
    $admin = adminUser();
    $role = Role::where('name', 'admin')->first();

    actingAs($admin)
        ->get(route('admin.roles.show', $role))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/roles/show')
            ->has('role'));
});

/*
 * |--------------------------------------------------------------------------
 * | Role Edit Tests
 * |--------------------------------------------------------------------------
 */

it('displays role edit page for admin', function () {
    $admin = adminUser();
    $role = Role::create([
        'name' => 'editable-role',
        'display_name' => 'Editable Role',
        'guard_name' => 'web',
        'is_default' => false,
    ]);

    actingAs($admin)
        ->get(route('admin.roles.edit', $role))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/roles/create-edit')
            ->has('role')
            ->has('permissions')
            ->where('mode', 'edit'));
});

/*
 * |--------------------------------------------------------------------------
 * | Role Update Tests
 * |--------------------------------------------------------------------------
 */

it('updates a role', function () {
    $admin = adminUser();
    $role = Role::create([
        'name' => 'test_update_role',
        'display_name' => 'Test Update Role',
        'guard_name' => 'web',
        'is_default' => false,
    ]);

    actingAs($admin)
        ->put(route('admin.roles.update', $role), [
            'name' => 'updated_role',
            'display_name' => 'Updated Role',
            'permissions' => [],
        ])
        ->assertRedirect(route('admin.roles.index'))
        ->assertSessionHas('success', 'Role updated successfully.');

    expect($role->fresh()->display_name)->toBe('Updated Role');
});

it('prevents updating default roles', function () {
    $admin = adminUser();
    $role = Role::where('is_default', true)->first();

    actingAs($admin)
        ->put(route('admin.roles.update', $role), [
            'name' => 'updated_name',
            'display_name' => 'Updated Name',
        ])
        ->assertRedirect(route('admin.roles.index'))
        ->assertSessionHas('error', 'Default roles cannot be updated.');
});

it('denies role update without permission', function () {
    $user = portalUser();
    $role = Role::where('is_default', false)->first() ?? Role::create([
        'name' => 'test-role-for-update',
        'display_name' => 'Test Role',
        'guard_name' => 'web',
        'is_default' => false,
    ]);

    actingAs($user)
        ->put(route('admin.roles.update', $role), [
            'name' => 'updated',
            'display_name' => 'Updated',
        ])
        // Portal users are redirected by admin.panel middleware
        ->assertRedirect();
});

/*
 * |--------------------------------------------------------------------------
 * | Role Delete Tests
 * |--------------------------------------------------------------------------
 */

it('deletes a role', function () {
    $admin = adminUser();
    $role = Role::create([
        'name' => 'deletable-role',
        'display_name' => 'Deletable Role',
        'guard_name' => 'web',
        'is_default' => false,
    ]);

    actingAs($admin)
        ->delete(route('admin.roles.destroy', $role))
        ->assertRedirect(route('admin.roles.index'))
        ->assertSessionHas('success', 'Role deleted successfully.');

    expect(Role::where('name', 'deletable-role')->exists())->toBeFalse();
});

it('denies role deletion without permission', function () {
    $user = portalUser();
    $role = Role::create([
        'name' => 'test-role-delete',
        'display_name' => 'Test Role',
        'guard_name' => 'web',
        'is_default' => false,
    ]);

    actingAs($user)
        ->delete(route('admin.roles.destroy', $role))
        // Portal users are redirected by admin.panel middleware
        ->assertRedirect();
});
