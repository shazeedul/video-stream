<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Services\TenantContextService;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('context service resolves and stores active tenant in session', function (): void {
    $user = User::factory()->create();
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $tenant1->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);
    $tenant2->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $service = app(TenantContextService::class);
    expect($service->getCurrentTenant($user)->id)->toBe($tenant1->id);

    $service->setCurrentTenant($user, $tenant2);
    expect($service->getCurrentTenant($user)->id)->toBe($tenant2->id);
});

// Review Focus 1: Orphaned User without Tenants
test('user without any tenants is automatically provisioned a personal organization on portal request', function (): void {
    $user = User::factory()->create(['name' => 'Jane Developer']);

    $response = $this->actingAs($user)->get(route('portal.dashboard'));

    $response->assertOk();
    expect($user->fresh()->tenants)->toHaveCount(1)
        ->and($user->fresh()->tenants->first()->name)->toContain('Jane Developer');
});

// Review Focus 4: Suspended Organization Access
test('user whose active organization is suspended is blocked from accessing portal', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['status' => TenantStatus::SUSPENDED]);
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $response = $this->actingAs($user)->get(route('portal.dashboard'));

    $response->assertForbidden();
});

test('context service clears current tenant from session', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);

    $service = app(TenantContextService::class);
    $service->setCurrentTenant($user, $tenant);
    expect(session('current_tenant_id'))->toBe($tenant->id);

    $service->clearCurrentTenant();
    expect(session('current_tenant_id'))->toBeNull();
});

test('context service refuses to set tenant if user is not a member', function (): void {
    $user = User::factory()->create();
    $unrelatedTenant = Tenant::factory()->create();

    $service = app(TenantContextService::class);
    $result = $service->setCurrentTenant($user, $unrelatedTenant);

    expect($result)->toBeFalse()
        ->and(session('current_tenant_id'))->toBeNull();
});

test('middleware binds current_tenant in container and shares currentTenant prop in Inertia', function (): void {
    $user = User::factory()->create(['name' => 'John Doe']);
    $tenant = Tenant::factory()->create(['name' => 'Acme Corp', 'status' => TenantStatus::ACTIVE]);
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::ADMIN->value]);

    $response = $this->actingAs($user)->get(route('portal.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('portal/dashboard')
        ->has('currentTenant', fn (Assert $prop) => $prop
            ->where('id', $tenant->id)
            ->where('name', 'Acme Corp')
            ->where('role', TenantRole::ADMIN->value)
            ->etc()
        )
    );
});

test('context service getActiveTenant returns container bound instance or resolves from user', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);

    $service = app(TenantContextService::class);

    // Without container binding, resolves from user
    expect($service->getActiveTenant($user)->id)->toBe($tenant->id);

    // With container binding, uses container instance
    $otherTenant = Tenant::factory()->create();
    app()->instance('current_tenant', $otherTenant);
    expect($service->getActiveTenant($user)->id)->toBe($otherTenant->id);
    expect($service->getActiveTenant(null)->id)->toBe($otherTenant->id);
});

test('user in a suspended tenant who belongs to another active tenant can switch to active tenant', function (): void {
    $user = User::factory()->create();
    $suspendedTenant = Tenant::factory()->create(['status' => TenantStatus::SUSPENDED]);
    $activeTenant = Tenant::factory()->create(['status' => TenantStatus::ACTIVE]);

    $suspendedTenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);
    $activeTenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $response = $this->actingAs($user)
        ->withSession(['current_tenant_id' => $suspendedTenant->id])
        ->post(route('portal.tenants.switch', $activeTenant));

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect(session('current_tenant_id'))->toBe($activeTenant->id);
});

test('user cannot switch into a suspended tenant', function (): void {
    $user = User::factory()->create();
    $activeTenant = Tenant::factory()->create(['status' => TenantStatus::ACTIVE]);
    $suspendedTenant = Tenant::factory()->create(['status' => TenantStatus::SUSPENDED]);

    $activeTenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);
    $suspendedTenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $response = $this->actingAs($user)
        ->withSession(['current_tenant_id' => $activeTenant->id])
        ->post(route('portal.tenants.switch', $suspendedTenant));

    $response->assertRedirect();
    $response->assertSessionHas('error', __('Cannot switch to a suspended organization.'));
    expect(session('current_tenant_id'))->toBe($activeTenant->id);
});

test('context service automatically switches to active tenant when session tenant is suspended', function (): void {
    $user = User::factory()->create();
    $suspendedTenant = Tenant::factory()->create(['status' => TenantStatus::SUSPENDED]);
    $activeTenant = Tenant::factory()->create(['status' => TenantStatus::ACTIVE]);

    $suspendedTenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);
    $activeTenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    session(['current_tenant_id' => $suspendedTenant->id]);

    $service = app(TenantContextService::class);
    $resolved = $service->getCurrentTenant($user);

    expect($resolved->id)->toBe($activeTenant->id)
        ->and(session('current_tenant_id'))->toBe($activeTenant->id);
});

test('context service retains suspended tenant when user has no other active tenants', function (): void {
    $user = User::factory()->create();
    $suspendedTenant = Tenant::factory()->create(['status' => TenantStatus::SUSPENDED]);

    $suspendedTenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    session(['current_tenant_id' => $suspendedTenant->id]);

    $service = app(TenantContextService::class);
    $resolved = $service->getCurrentTenant($user);

    expect($resolved->id)->toBe($suspendedTenant->id)
        ->and(session('current_tenant_id'))->toBe($suspendedTenant->id);
});
