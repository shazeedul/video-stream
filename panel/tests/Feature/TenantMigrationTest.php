<?php

declare(strict_types=1);

use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Enums\TenantUserStatus;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

test('tenant tables exist with expected schema columns', function (): void {
    expect(Schema::hasTable('tenants'))->toBeTrue()
        ->and(Schema::hasColumns('tenants', ['id', 'name', 'slug', 'status', 'settings', 'created_at', 'updated_at', 'deleted_at']))->toBeTrue();

    expect(Schema::hasTable('tenant_user'))->toBeTrue()
        ->and(Schema::hasColumns('tenant_user', ['id', 'tenant_id', 'user_id', 'role', 'status', 'created_at', 'updated_at']))->toBeTrue();
});

test('tenant enums contain required cases and label implementations', function (): void {
    expect(TenantStatus::ACTIVE->value)->toBe('active')
        ->and(TenantStatus::SUSPENDED->value)->toBe('suspended')
        ->and(TenantStatus::ARCHIVED->value)->toBe('archived')
        ->and(TenantStatus::ACTIVE->label())->toBe('Active');

    expect(TenantRole::OWNER->value)->toBe('owner')
        ->and(TenantRole::ADMIN->value)->toBe('admin')
        ->and(TenantRole::MEMBER->value)->toBe('member')
        ->and(TenantRole::VIEWER->value)->toBe('viewer')
        ->and(TenantRole::OWNER->isOwner())->toBeTrue()
        ->and(TenantRole::ADMIN->isOwner())->toBeFalse();

    expect(TenantUserStatus::ACTIVE->value)->toBe('active')
        ->and(TenantUserStatus::INVITED->value)->toBe('invited')
        ->and(TenantUserStatus::SUSPENDED->value)->toBe('suspended');
});

test('tenant status enum provides labels, colors, and boolean helpers', function (): void {
    expect(TenantStatus::ACTIVE->label())->toBe('Active')
        ->and(TenantStatus::SUSPENDED->label())->toBe('Suspended')
        ->and(TenantStatus::ARCHIVED->label())->toBe('Archived')
        ->and(TenantStatus::ACTIVE->color())->toBe('green')
        ->and(TenantStatus::SUSPENDED->color())->toBe('red')
        ->and(TenantStatus::ARCHIVED->color())->toBe('gray')
        ->and(TenantStatus::ACTIVE->isActive())->toBeTrue()
        ->and(TenantStatus::ACTIVE->isSuspended())->toBeFalse()
        ->and(TenantStatus::ACTIVE->isArchived())->toBeFalse()
        ->and(TenantStatus::SUSPENDED->isSuspended())->toBeTrue()
        ->and(TenantStatus::ARCHIVED->isArchived())->toBeTrue();
});

test('tenant role enum provides permission checks and metadata', function (): void {
    expect(TenantRole::OWNER->label())->toBe('Owner')
        ->and(TenantRole::ADMIN->label())->toBe('Admin')
        ->and(TenantRole::MEMBER->label())->toBe('Member')
        ->and(TenantRole::VIEWER->label())->toBe('Viewer')
        ->and(TenantRole::OWNER->canManageMembers())->toBeTrue()
        ->and(TenantRole::ADMIN->canManageMembers())->toBeTrue()
        ->and(TenantRole::MEMBER->canManageMembers())->toBeFalse()
        ->and(TenantRole::VIEWER->canManageMembers())->toBeFalse()
        ->and(TenantRole::OWNER->canManageVideos())->toBeTrue()
        ->and(TenantRole::ADMIN->canManageVideos())->toBeTrue()
        ->and(TenantRole::MEMBER->canManageVideos())->toBeTrue()
        ->and(TenantRole::VIEWER->canManageVideos())->toBeFalse()
        ->and(TenantRole::OWNER->isOwner())->toBeTrue()
        ->and(TenantRole::ADMIN->isAdmin())->toBeTrue()
        ->and(TenantRole::MEMBER->isMember())->toBeTrue()
        ->and(TenantRole::VIEWER->isViewer())->toBeTrue();
});

test('tenant user status enum provides labels, colors, and helpers', function (): void {
    expect(TenantUserStatus::ACTIVE->label())->toBe('Active')
        ->and(TenantUserStatus::INVITED->label())->toBe('Invited')
        ->and(TenantUserStatus::SUSPENDED->label())->toBe('Suspended')
        ->and(TenantUserStatus::ACTIVE->color())->toBe('green')
        ->and(TenantUserStatus::INVITED->color())->toBe('blue')
        ->and(TenantUserStatus::SUSPENDED->color())->toBe('red')
        ->and(TenantUserStatus::ACTIVE->isActive())->toBeTrue()
        ->and(TenantUserStatus::INVITED->isInvited())->toBeTrue()
        ->and(TenantUserStatus::SUSPENDED->isSuspended())->toBeTrue();
});

test('migrations can rollback cleanly', function (): void {
    Artisan::call('migrate:rollback', ['--step' => 2]);

    expect(Schema::hasTable('tenant_user'))->toBeFalse()
        ->and(Schema::hasTable('tenants'))->toBeFalse();

    Artisan::call('migrate');

    expect(Schema::hasTable('tenants'))->toBeTrue()
        ->and(Schema::hasTable('tenant_user'))->toBeTrue();
});
