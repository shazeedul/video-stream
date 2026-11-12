<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\Video;
use App\Enums\TenantRole;
use App\Enums\TenantUserStatus;
use App\Models\User;

test('tenant viewer can view videos but cannot create, update, or delete', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->create();
    $tenant->users()->attach($viewer->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::VIEWER->value]);

    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id]);

    expect($viewer->can('view', $video))->toBeTrue()
        ->and($viewer->can('viewAny', Video::class))->toBeTrue()
        ->and($viewer->can('create', Video::class))->toBeFalse()
        ->and($viewer->can('update', $video))->toBeFalse()
        ->and($viewer->can('delete', $video))->toBeFalse();
});

test('tenant member can create videos and update their own video but cannot delete', function (): void {
    $tenant = Tenant::factory()->create();
    $member = User::factory()->create();
    $otherUser = User::factory()->create();
    $tenant->users()->attach($member->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    app()->instance('current_tenant', $tenant);
    $ownVideo = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $member->id]);
    $otherVideo = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $otherUser->id]);

    expect($member->can('viewAny', Video::class))->toBeTrue()
        ->and($member->can('create', Video::class))->toBeTrue()
        ->and($member->can('update', $ownVideo))->toBeTrue()
        ->and($member->can('update', $otherVideo))->toBeFalse()
        ->and($member->can('delete', $ownVideo))->toBeFalse();
});

test('tenant admin and owner can manage and delete any tenant video', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    $otherUser = User::factory()->create();
    $tenant->users()->attach($admin->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::ADMIN->value]);

    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $otherUser->id]);

    expect($admin->can('viewAny', Video::class))->toBeTrue()
        ->and($admin->can('view', $video))->toBeTrue()
        ->and($admin->can('create', Video::class))->toBeTrue()
        ->and($admin->can('update', $video))->toBeTrue()
        ->and($admin->can('delete', $video))->toBeTrue();
});

test('tenant owner can manage and delete any tenant video', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $tenant->users()->attach($owner->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);

    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $otherUser->id]);

    expect($owner->can('viewAny', Video::class))->toBeTrue()
        ->and($owner->can('view', $video))->toBeTrue()
        ->and($owner->can('create', Video::class))->toBeTrue()
        ->and($owner->can('update', $video))->toBeTrue()
        ->and($owner->can('delete', $video))->toBeTrue();
});

test('platform admin can view, create, update, and delete any video across tenants', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $tenant = Tenant::factory()->create();
    $otherUser = User::factory()->create();

    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $otherUser->id]);

    expect($admin->can('viewAny', Video::class))->toBeTrue()
        ->and($admin->can('view', $video))->toBeTrue()
        ->and($admin->can('create', Video::class))->toBeTrue()
        ->and($admin->can('update', $video))->toBeTrue()
        ->and($admin->can('delete', $video))->toBeTrue();
});

test('user cannot update or delete video belonging to another tenant', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $adminA = User::factory()->create();
    $tenantA->users()->attach($adminA->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::ADMIN->value]);

    app()->instance('current_tenant', $tenantB);
    $videoB = Video::factory()->create(['tenant_id' => $tenantB->id]);

    expect($adminA->can('view', $videoB))->toBeFalse()
        ->and($adminA->can('update', $videoB))->toBeFalse()
        ->and($adminA->can('delete', $videoB))->toBeFalse();
});

test('suspended member cannot view, create, update, or delete video', function (): void {
    $tenant = Tenant::factory()->create();
    $suspendedUser = User::factory()->create();
    $tenant->users()->attach($suspendedUser->id, [
        'id' => (string) str()->uuid(),
        'role' => TenantRole::OWNER->value,
        'status' => TenantUserStatus::SUSPENDED->value,
    ]);

    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $suspendedUser->id]);

    expect($suspendedUser->can('viewAny', Video::class))->toBeFalse()
        ->and($suspendedUser->can('view', $video))->toBeFalse()
        ->and($suspendedUser->can('create', Video::class))->toBeFalse()
        ->and($suspendedUser->can('update', $video))->toBeFalse()
        ->and($suspendedUser->can('delete', $video))->toBeFalse();
});

test('unrelated user cannot view, create, update, or delete video', function (): void {
    $tenant = Tenant::factory()->create();
    $stranger = User::factory()->create();

    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id]);

    expect($stranger->can('viewAny', Video::class))->toBeFalse()
        ->and($stranger->can('view', $video))->toBeFalse()
        ->and($stranger->can('create', Video::class))->toBeFalse()
        ->and($stranger->can('update', $video))->toBeFalse()
        ->and($stranger->can('delete', $video))->toBeFalse();
});

test('invited member cannot view, create, update, or delete video', function (): void {
    $tenant = Tenant::factory()->create();
    $invitedUser = User::factory()->create();
    $tenant->users()->attach($invitedUser->id, [
        'id' => (string) str()->uuid(),
        'role' => TenantRole::ADMIN->value,
        'status' => TenantUserStatus::INVITED->value,
    ]);

    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id]);

    expect($invitedUser->can('viewAny', Video::class))->toBeFalse()
        ->and($invitedUser->can('view', $video))->toBeFalse()
        ->and($invitedUser->can('create', Video::class))->toBeFalse()
        ->and($invitedUser->can('update', $video))->toBeFalse()
        ->and($invitedUser->can('delete', $video))->toBeFalse();
});

test('platform admin can viewAny videos without current tenant context', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    expect($admin->can('viewAny', Video::class))->toBeTrue()
        ->and($admin->can('create', Video::class))->toBeTrue();
});

test('regular user cannot viewAny or create video without current tenant context', function (): void {
    $user = User::factory()->create();

    expect($user->can('viewAny', Video::class))->toBeFalse()
        ->and($user->can('create', Video::class))->toBeFalse();
});

test('viewer cannot update video even if viewer is set as creator', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->create();
    $tenant->users()->attach($viewer->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::VIEWER->value]);

    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $viewer->id]);

    expect($viewer->can('update', $video))->toBeFalse()
        ->and($viewer->can('delete', $video))->toBeFalse();
});

test('restore and forceDelete follow deletion authorization rules', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $tenant->users()->attach($admin->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::ADMIN->value]);
    $tenant->users()->attach($member->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $member->id]);

    expect($admin->can('restore', $video))->toBeTrue()
        ->and($admin->can('forceDelete', $video))->toBeTrue()
        ->and($member->can('restore', $video))->toBeFalse()
        ->and($member->can('forceDelete', $video))->toBeFalse();
});
