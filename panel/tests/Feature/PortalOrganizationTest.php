<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Enums\TenantRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('user can switch between organizations they belong to', function (): void {
    $user = User::factory()->create();
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $tenant1->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);
    $tenant2->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $response = $this->actingAs($user)->post(route('portal.tenants.switch', $tenant2));

    $response->assertRedirect();
    $response->assertSessionHas('success', __('Switched active organization.'));
    expect(session('current_tenant_id'))->toBe($tenant2->id);
});

// Review Focus 3: Cross-Tenant Context Spoofing
test('user cannot switch to an organization they are not a member of', function (): void {
    $user = User::factory()->create();
    $myTenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $myTenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);

    $response = $this->actingAs($user)->post(route('portal.tenants.switch', $otherTenant));

    $response->assertForbidden();
    expect(session('current_tenant_id'))->not()->toBe($otherTenant->id);
});

test('organization owner can update organization name and slug', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);

    session(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)->put(route('portal.settings.organization.update'), [
        'name' => 'Updated Studio',
        'slug' => 'updated-studio',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', __('Organization updated successfully.'));
    expect($tenant->fresh()->name)->toBe('Updated Studio')
        ->and($tenant->fresh()->slug)->toBe('updated-studio');
});

test('organization member can view organization overview', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['name' => 'Test Studio', 'slug' => 'test-studio']);
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    session(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)->get(route('portal.settings.organization.overview'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('portal/settings/organization/overview')
        ->where('organization.id', $tenant->id)
        ->where('organization.name', 'Test Studio')
        ->where('organization.slug', 'test-studio')
        ->where('userRole', TenantRole::MEMBER->value)
    );
});

test('regular member cannot update organization settings', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['name' => 'Locked Studio', 'slug' => 'locked-studio']);
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    session(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)->put(route('portal.settings.organization.update'), [
        'name' => 'Hacked Studio',
        'slug' => 'hacked-studio',
    ]);

    $response->assertForbidden();
    expect($tenant->fresh()->name)->toBe('Locked Studio');
});

test('slug must be unique ignoring current tenant', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['name' => 'My Org', 'slug' => 'my-org']);
    $existingTenant = Tenant::factory()->create(['name' => 'Existing Org', 'slug' => 'existing-org']);
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);

    session(['current_tenant_id' => $tenant->id]);

    // Updating with same slug should pass
    $response = $this->actingAs($user)->put(route('portal.settings.organization.update'), [
        'name' => 'My Renamed Org',
        'slug' => 'my-org',
    ]);
    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    // Updating with conflicting slug should fail validation
    $response2 = $this->actingAs($user)->put(route('portal.settings.organization.update'), [
        'name' => 'My Renamed Org',
        'slug' => 'existing-org',
    ]);
    $response2->assertSessionHasErrors(['slug']);
});

test('switching tenant flashes error when setCurrentTenant returns false', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $mockContextService = Mockery::mock(App\Domain\Tenants\Services\TenantContextService::class);
    $mockContextService->shouldReceive('getCurrentTenant')->andReturn($tenant);
    $mockContextService->shouldReceive('setCurrentTenant')->andReturn(false);
    app()->instance(App\Domain\Tenants\Services\TenantContextService::class, $mockContextService);

    $response = $this->actingAs($user)->post(route('portal.tenants.switch', $tenant));

    $response->assertRedirect();
    $response->assertSessionHas('error', __('Failed to switch active organization.'));
});
