<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\delete;

/*
 * |--------------------------------------------------------------------------
 * | User Index Tests
 * |--------------------------------------------------------------------------
 */

it('displays users index page for admin', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/index')
            ->has('users')
            ->has('roles')
            ->has('filters'));
});

it('denies access to users index for unauthorized users', function () {
    $user = portalUser();

    // Portal users are redirected by admin.panel middleware
    actingAs($user)
        ->get(route('admin.users.index'))
        ->assertRedirect();
});

it('filters users by search query', function () {
    $admin = adminUser();
    User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
    User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

    actingAs($admin)
        ->get(route('admin.users.index', ['search' => 'John']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.search', 'John'));
});

it('filters users by verification status', function () {
    $admin = adminUser();
    User::factory()->create(['email_verified_at' => now()]);
    User::factory()->unverified()->create();

    actingAs($admin)
        ->get(route('admin.users.index', ['status' => 'verified']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.status', 'verified'));
});

it('filters users by role', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get(route('admin.users.index', ['role' => 'admin']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.role', 'admin'));
});

it('sorts users by name', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get(route('admin.users.index', ['sort' => 'name']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'name'));
});

it('sorts users by created date', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get(route('admin.users.index', ['sort' => 'created_at']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'created_at'));
});

/*
 * |--------------------------------------------------------------------------
 * | User Store Tests
 * |--------------------------------------------------------------------------
 */

it('creates a new user', function () {
    $admin = adminUser();
    $role = Role::where('name', 'user')->first();

    $userData = [
        'name' => 'New Test User',
        'email' => 'newuser@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'roles' => [$role->id],
    ];

    actingAs($admin)
        ->post(route('admin.users.store'), $userData)
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success', 'User created successfully.');

    assertDatabaseHas('users', [
        'name' => 'New Test User',
        'email' => 'newuser@example.com',
    ]);
});

it('denies user creation without permission', function () {
    $user = portalUser();

    // Portal users are redirected by admin.panel middleware
    actingAs($user)
        ->post(route('admin.users.store'), [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertRedirect();
});

it('validates required fields when creating user', function () {
    $admin = adminUser();

    actingAs($admin)
        ->post(route('admin.users.store'), [])
        ->assertSessionHasErrors(['name', 'email', 'password']);
});

/*
 * |--------------------------------------------------------------------------
 * | User Show Tests
 * |--------------------------------------------------------------------------
 */

it('returns user details as JSON', function () {
    $admin = adminUser();
    $user = User::factory()->create();
    $user->assignRole('user');

    // Route requires AJAX headers
    actingAs($admin)
        ->get(route('admin.users.show', $user), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertJsonStructure([
            'user' => [
                'id',
                'name',
                'email',
                'roles',
            ],
        ]);
});

/*
 * |--------------------------------------------------------------------------
 * | User Update Tests
 * |--------------------------------------------------------------------------
 */

it('updates a user', function () {
    $admin = adminUser();
    $user = User::factory()->create(['name' => 'Old Name']);
    $user->assignRole('user');

    actingAs($admin)
        ->put(route('admin.users.update', $user), [
            'name' => 'Updated Name',
            'email' => $user->email,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success', 'User updated successfully.');

    expect($user->fresh()->name)->toBe('Updated Name');
});

it('denies user update without permission', function () {
    $user = portalUser();
    $targetUser = User::factory()->create();

    // Portal users are redirected by admin.panel middleware
    actingAs($user)
        ->put(route('admin.users.update', $targetUser), [
            'name' => 'New Name',
            'email' => $targetUser->email,
        ])
        ->assertRedirect();
});

/*
 * |--------------------------------------------------------------------------
 * | User Delete Tests
 * |--------------------------------------------------------------------------
 */

it('deletes a user', function () {
    $admin = adminUser();
    $user = User::factory()->create();

    actingAs($admin)
        ->delete(route('admin.users.destroy', $user))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success', 'User deleted successfully.');

    assertSoftDeleted('users', ['id' => $user->id]);
});

it('denies user deletion without permission', function () {
    $user = portalUser();
    $targetUser = User::factory()->create();

    // Portal users are redirected by admin.panel middleware
    actingAs($user)
        ->delete(route('admin.users.destroy', $targetUser))
        ->assertRedirect();
});

/*
 * |--------------------------------------------------------------------------
 * | User Verify Email Tests
 * |--------------------------------------------------------------------------
 */

it('verifies user email', function () {
    $admin = adminUser();
    $user = User::factory()->unverified()->create();

    actingAs($admin)
        ->post(route('admin.users.verify-email', $user))
        ->assertRedirect()
        ->assertSessionHas('success', 'User email verified successfully.');

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

/*
 * |--------------------------------------------------------------------------
 * | User Suspend/Unsuspend Tests
 * |--------------------------------------------------------------------------
 */

it('suspends a user with reason', function () {
    $admin = adminUser();
    $user = User::factory()->create();

    actingAs($admin)
        ->post(route('admin.users.suspend', $user), [
            'reason' => 'Violation of terms',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'User suspended successfully.');

    expect($user->fresh()->isSuspended())->toBeTrue();
    expect($user->fresh()->status_reason)->toBe('Violation of terms');
});

it('requires reason when suspending user', function () {
    $admin = adminUser();
    $user = User::factory()->create();

    actingAs($admin)
        ->post(route('admin.users.suspend', $user), [])
        ->assertSessionHasErrors(['reason']);
});

it('unsuspends a user', function () {
    $admin = adminUser();
    $user = User::factory()->suspended()->create();

    actingAs($admin)
        ->post(route('admin.users.unsuspend', $user))
        ->assertRedirect()
        ->assertSessionHas('success', 'User unsuspend successfully.');

    expect($user->fresh()->isActive())->toBeTrue();
});

it('denies suspend action without permission', function () {
    $user = portalUser();
    $targetUser = User::factory()->create();

    // Portal users are redirected by admin.panel middleware
    actingAs($user)
        ->post(route('admin.users.suspend', $targetUser), [
            'reason' => 'Test',
        ])
        ->assertRedirect();
});
