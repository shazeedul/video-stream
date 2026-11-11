<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Enums\TenantRole;
use App\Enums\TenantUserStatus;
use App\Models\User;

test('platform admin can view and update any tenant', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $tenant = Tenant::factory()->create();

    expect($admin->can('view', $tenant))->toBeTrue()
        ->and($admin->can('update', $tenant))->toBeTrue()
        ->and($admin->can('delete', $tenant))->toBeTrue()
        ->and($admin->can('manageMembers', $tenant))->toBeTrue()
        ->and($admin->can('viewAny', Tenant::class))->toBeTrue();
});

test('tenant owner can update tenant and manage members', function (): void {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($owner->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);

    expect($owner->can('view', $tenant))->toBeTrue()
        ->and($owner->can('update', $tenant))->toBeTrue()
        ->and($owner->can('delete', $tenant))->toBeTrue()
        ->and($owner->can('manageMembers', $tenant))->toBeTrue()
        ->and($owner->can('viewAny', Tenant::class))->toBeFalse();
});

test('tenant admin can update tenant and manage members but cannot delete', function (): void {
    $admin = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($admin->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::ADMIN->value]);

    expect($admin->can('view', $tenant))->toBeTrue()
        ->and($admin->can('update', $tenant))->toBeTrue()
        ->and($admin->can('delete', $tenant))->toBeFalse()
        ->and($admin->can('manageMembers', $tenant))->toBeTrue();
});

test('tenant member cannot update tenant or manage members', function (): void {
    $member = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($member->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    expect($member->can('view', $tenant))->toBeTrue()
        ->and($member->can('update', $tenant))->toBeFalse()
        ->and($member->can('delete', $tenant))->toBeFalse()
        ->and($member->can('manageMembers', $tenant))->toBeFalse();
});

test('tenant viewer can view but cannot update or manage members', function (): void {
    $viewer = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($viewer->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::VIEWER->value]);

    expect($viewer->can('view', $tenant))->toBeTrue()
        ->and($viewer->can('update', $tenant))->toBeFalse()
        ->and($viewer->can('delete', $tenant))->toBeFalse()
        ->and($viewer->can('manageMembers', $tenant))->toBeFalse();
});

test('unrelated user cannot view or manage tenant', function (): void {
    $stranger = User::factory()->create();
    $tenant = Tenant::factory()->create();

    expect($stranger->can('view', $tenant))->toBeFalse()
        ->and($stranger->can('update', $tenant))->toBeFalse()
        ->and($stranger->can('delete', $tenant))->toBeFalse()
        ->and($stranger->can('manageMembers', $tenant))->toBeFalse();
});

test('authenticated user can create tenant', function (): void {
    $user = User::factory()->create();

    expect($user->can('create', Tenant::class))->toBeTrue();
});

test('suspended member cannot perform actions authorized by tenant policy', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($user->id, [
        'id' => (string) str()->uuid(),
        'role' => TenantRole::OWNER->value,
        'status' => TenantUserStatus::SUSPENDED->value,
    ]);

    expect($user->can('view', $tenant))->toBeFalse()
        ->and($user->can('update', $tenant))->toBeFalse()
        ->and($user->can('delete', $tenant))->toBeFalse()
        ->and($user->can('manageMembers', $tenant))->toBeFalse();
});

test('invited member cannot perform actions authorized by tenant policy', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($user->id, [
        'id' => (string) str()->uuid(),
        'role' => TenantRole::ADMIN->value,
        'status' => TenantUserStatus::INVITED->value,
    ]);

    expect($user->can('view', $tenant))->toBeFalse()
        ->and($user->can('update', $tenant))->toBeFalse()
        ->and($user->can('delete', $tenant))->toBeFalse()
        ->and($user->can('manageMembers', $tenant))->toBeFalse();
});
