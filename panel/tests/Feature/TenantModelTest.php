<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Models\TenantUser;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Enums\TenantUserStatus;
use App\Models\User;

test('tenant can be created via factory and has enum cast', function (): void {
    $tenant = Tenant::factory()->create([
        'name' => 'Acme Media',
        'status' => TenantStatus::ACTIVE,
    ]);

    expect($tenant->id)->toBeString()
        ->and($tenant->status)->toBe(TenantStatus::ACTIVE)
        ->and($tenant->status->isActive())->toBeTrue();
});

test('tenant belongs to many users through tenant_user pivot', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $tenant->users()->attach($owner->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);
    $tenant->users()->attach($member->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    expect($tenant->users)->toHaveCount(2)
        ->and($owner->tenants)->toHaveCount(1)
        ->and($owner->isTenantOwner($tenant))->toBeTrue()
        ->and($member->isTenantOwner($tenant))->toBeFalse()
        ->and($member->isTenantMember($tenant))->toBeTrue()
        ->and($owner->tenantRole($tenant))->toBe(TenantRole::OWNER);
});

test('tenant scopes filter active and search by name or slug', function (): void {
    $active = Tenant::factory()->create(['name' => 'Alpha Studio', 'slug' => 'alpha-studio', 'status' => TenantStatus::ACTIVE]);
    $suspended = Tenant::factory()->create(['name' => 'Beta Media', 'slug' => 'beta-media', 'status' => TenantStatus::SUSPENDED]);

    expect(Tenant::query()->active()->pluck('id'))->toContain($active->id)
        ->and(Tenant::query()->active()->pluck('id'))->not->toContain($suspended->id);

    expect(Tenant::query()->search('Alpha')->pluck('id'))->toContain($active->id)
        ->and(Tenant::query()->search('alpha')->pluck('id'))->toContain($active->id)
        ->and(Tenant::query()->search('BETA')->pluck('id'))->toContain($suspended->id)
        ->and(Tenant::query()->search('beta-media')->pluck('id'))->toContain($suspended->id)
        ->and(Tenant::query()->search('NonExistent')->count())->toBe(0);
});

test('tenant and user memberships relationships', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $membership = TenantUser::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantRole::ADMIN,
        'status' => TenantUserStatus::ACTIVE,
    ]);

    expect($tenant->memberships)->toHaveCount(1)
        ->and($tenant->memberships->first()->id)->toBe($membership->id)
        ->and($user->tenantMemberships)->toHaveCount(1)
        ->and($user->tenantMemberships->first()->id)->toBe($membership->id)
        ->and($membership->tenant->id)->toBe($tenant->id)
        ->and($membership->user->id)->toBe($user->id)
        ->and($membership->role)->toBe(TenantRole::ADMIN)
        ->and($membership->status)->toBe(TenantUserStatus::ACTIVE);
});

test('user tenant helper methods return expected values when not a member', function (): void {
    $tenant = Tenant::factory()->create();
    $unrelatedUser = User::factory()->create();

    expect($unrelatedUser->isTenantOwner($tenant))->toBeFalse()
        ->and($unrelatedUser->isTenantMember($tenant))->toBeFalse()
        ->and($unrelatedUser->tenantRole($tenant))->toBeNull();
});

test('user with suspended membership returns null for tenantRole and false for isTenantMember and isTenantOwner', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $tenant->users()->attach($user->id, [
        'id' => (string) str()->uuid(),
        'role' => TenantRole::OWNER->value,
        'status' => TenantUserStatus::SUSPENDED->value,
    ]);

    expect($user->tenantRole($tenant))->toBeNull()
        ->and($user->isTenantMember($tenant))->toBeFalse()
        ->and($user->isTenantOwner($tenant))->toBeFalse();

    // Verify when relation is loaded
    $userWithRelation = User::query()->with('tenants')->find($user->id);
    expect($userWithRelation->tenantRole($tenant))->toBeNull()
        ->and($userWithRelation->isTenantMember($tenant))->toBeFalse()
        ->and($userWithRelation->isTenantOwner($tenant))->toBeFalse();
});

test('user with invited membership returns null for tenantRole and false for isTenantMember and isTenantOwner', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $tenant->users()->attach($user->id, [
        'id' => (string) str()->uuid(),
        'role' => TenantRole::ADMIN->value,
        'status' => TenantUserStatus::INVITED->value,
    ]);

    expect($user->tenantRole($tenant))->toBeNull()
        ->and($user->isTenantMember($tenant))->toBeFalse()
        ->and($user->isTenantOwner($tenant))->toBeFalse();

    // Verify when relation is loaded
    $userWithRelation = User::query()->with('tenants')->find($user->id);
    expect($userWithRelation->tenantRole($tenant))->toBeNull()
        ->and($userWithRelation->isTenantMember($tenant))->toBeFalse()
        ->and($userWithRelation->isTenantOwner($tenant))->toBeFalse();
});
