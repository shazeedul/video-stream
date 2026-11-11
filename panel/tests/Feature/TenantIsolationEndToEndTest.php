<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Models\User;
use Database\Seeders\TenantSeeder;

test('end-to-end multi-tenant lifecycle and boundary enforcement', function (): void {
    // 1. Setup Tenant A and Tenant B
    $ownerA = User::factory()->create(['name' => 'Alice Owner', 'email' => 'alice@company-a.com']);
    $memberA = User::factory()->create(['name' => 'Adam Member', 'email' => 'adam@company-a.com']);
    $tenantA = Tenant::factory()->create(['name' => 'Company A', 'slug' => 'company-a']);
    $tenantA->users()->attach($ownerA->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);
    $tenantA->users()->attach($memberA->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $ownerB = User::factory()->create(['name' => 'Bob Owner', 'email' => 'bob@company-b.com']);
    $tenantB = Tenant::factory()->create(['name' => 'Company B', 'slug' => 'company-b']);
    $tenantB->users()->attach($ownerB->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);

    // 2. Alice views Company A organization settings
    $this->actingAs($ownerA)->get(route('portal.settings.organization.overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('organization.id', $tenantA->id));

    // 3. Alice cannot modify Company B
    $this->actingAs($ownerA)->put(route('portal.settings.organization.update'), [
        'name' => 'Hacked Company B',
        'slug' => 'company-a',
    ])->assertSessionHasNoErrors();
    expect($tenantB->fresh()->name)->toBe('Company B');

    // Alice also cannot hijack Company B's slug
    $this->actingAs($ownerA)->put(route('portal.settings.organization.update'), [
        'name' => 'Company A',
        'slug' => 'company-b',
    ])->assertSessionHasErrors(['slug']);

    // 4. Bob cannot view Company A members or tamper with them
    $pivotA = $tenantA->memberships()->where('user_id', $memberA->id)->first();
    $this->actingAs($ownerB)->delete(route('portal.settings.organization.members.destroy', $pivotA))
        ->assertNotFound();
    expect($tenantA->users()->count())->toBe(2);

    // Bob cannot switch into Company A
    $this->actingAs($ownerB)->post(route('portal.tenants.switch', $tenantA))
        ->assertForbidden();

    // 5. Suspending Company A locks out both Alice and Adam
    $tenantA->update(['status' => TenantStatus::SUSPENDED]);
    $this->actingAs($ownerA)->get(route('portal.dashboard'))->assertForbidden();
    $this->actingAs($memberA)->get(route('portal.dashboard'))->assertForbidden();

    // 6. Company B remains completely accessible
    $this->actingAs($ownerB)->get(route('portal.dashboard'))->assertOk();
});

test('tenant seeder provisions realistic demo organizations with isolated memberships', function (): void {
    $this->seed(TenantSeeder::class);

    $acme = Tenant::query()->where('slug', 'acme-streaming-studio')->first();
    expect($acme)->not()->toBeNull()
        ->and($acme->name)->toBe('Acme Streaming Studio')
        ->and($acme->status)->toBe(TenantStatus::ACTIVE);

    $nexus = Tenant::query()->where('slug', 'nexus-media-labs')->first();
    expect($nexus)->not()->toBeNull()
        ->and($nexus->name)->toBe('Nexus Media Labs')
        ->and($nexus->status)->toBe(TenantStatus::ACTIVE);

    // Verify Acme roles
    $studioOwner = User::query()->where('email', 'studio-owner@example.com')->first();
    $studioAdmin = User::query()->where('email', 'studio-admin@example.com')->first();
    $studioEditor = User::query()->where('email', 'studio-editor@example.com')->first();
    $studioViewer = User::query()->where('email', 'studio-viewer@example.com')->first();

    expect($studioOwner)->not()->toBeNull()
        ->and($studioAdmin)->not()->toBeNull()
        ->and($studioEditor)->not()->toBeNull()
        ->and($studioViewer)->not()->toBeNull();

    expect($studioOwner->tenantRole($acme))->toBe(TenantRole::OWNER)
        ->and($studioAdmin->tenantRole($acme))->toBe(TenantRole::ADMIN)
        ->and($studioEditor->tenantRole($acme))->toBe(TenantRole::MEMBER)
        ->and($studioViewer->tenantRole($acme))->toBe(TenantRole::VIEWER);

    // Verify Nexus isolated members
    $nexusOwner = User::query()->where('email', 'nexus-owner@example.com')->first();
    $nexusAdmin = User::query()->where('email', 'nexus-admin@example.com')->first();
    $nexusEditor = User::query()->where('email', 'nexus-editor@example.com')->first();
    $nexusViewer = User::query()->where('email', 'nexus-viewer@example.com')->first();

    expect($nexusOwner)->not()->toBeNull()
        ->and($nexusAdmin)->not()->toBeNull()
        ->and($nexusEditor)->not()->toBeNull()
        ->and($nexusViewer)->not()->toBeNull();

    expect($nexusOwner->tenantRole($nexus))->toBe(TenantRole::OWNER)
        ->and($nexusAdmin->tenantRole($nexus))->toBe(TenantRole::ADMIN)
        ->and($nexusEditor->tenantRole($nexus))->toBe(TenantRole::MEMBER)
        ->and($nexusViewer->tenantRole($nexus))->toBe(TenantRole::VIEWER);

    // Verify cross-tenant isolation: Nexus owner is not a member of Acme, and Studio owner is not a member of Nexus
    expect($nexusOwner->isTenantMember($acme))->toBeFalse()
        ->and($studioOwner->isTenantMember($nexus))->toBeFalse();
});
