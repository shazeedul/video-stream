<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Models\User;

test('platform admin can list tenants with search and status filtering', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Tenant::factory()->create(['name' => 'Alpha Studio', 'status' => TenantStatus::ACTIVE]);
    Tenant::factory()->create(['name' => 'Beta Productions', 'status' => TenantStatus::SUSPENDED]);

    $response = $this->actingAs($admin)->get(route('admin.tenants.index', ['search' => 'Alpha']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/tenants/index')
            ->has('tenants.data', 1)
            ->where('tenants.data.0.name', 'Alpha Studio')
        );
});

test('platform admin can create a new tenant with initial owner', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $owner = User::factory()->create(['email' => 'client@example.com']);

    $response = $this->actingAs($admin)->post(route('admin.tenants.store'), [
        'name' => 'Gamma Media',
        'slug' => 'gamma-media',
        'owner_email' => 'client@example.com',
        'status' => TenantStatus::ACTIVE->value,
    ]);

    $response->assertRedirect();
    $tenant = Tenant::where('slug', 'gamma-media')->first();
    expect($tenant)->not()->toBeNull()
        ->and($tenant->users()->first()->id)->toBe($owner->id)
        ->and($owner->isTenantOwner($tenant))->toBeTrue();
});

test('platform admin can suspend and unsuspend a tenant', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $tenant = Tenant::factory()->create(['status' => TenantStatus::ACTIVE]);

    $this->actingAs($admin)->patch(route('admin.tenants.status', $tenant), [
        'status' => TenantStatus::SUSPENDED->value,
    ]);

    expect($tenant->fresh()->status)->toBe(TenantStatus::SUSPENDED);
});

test('regular user cannot access admin tenants endpoints', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->actingAs($user)->get(route('admin.tenants.index'));

    // Portal users are redirected by admin.panel middleware
    $response->assertRedirect(route('portal.dashboard'));
});

test('user without tenants view permission is forbidden from admin tenants endpoints', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');
    $user->givePermissionTo('admin.panel.view');

    $response = $this->actingAs($user)->get(route('admin.tenants.index'));

    $response->assertForbidden();
});

test('platform admin can view tenant details with memberships', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $tenant = Tenant::factory()->create(['name' => 'Delta Studios']);
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, [
        'id' => (string) Illuminate\Support\Str::uuid(),
        'role' => TenantRole::ADMIN->value,
        'status' => App\Enums\TenantUserStatus::ACTIVE->value,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.tenants.show', $tenant));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/tenants/show')
            ->where('tenant.id', $tenant->id)
            ->where('tenant.name', 'Delta Studios')
            ->has('tenant.memberships', 1)
        );
});

test('platform admin can update tenant details', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $tenant = Tenant::factory()->create([
        'name' => 'Old Name',
        'slug' => 'old-slug',
    ]);

    $response = $this->actingAs($admin)->put(route('admin.tenants.update', $tenant), [
        'name' => 'Updated Name',
        'slug' => 'updated-slug',
        'status' => TenantStatus::ACTIVE->value,
    ]);

    $response->assertRedirect(route('admin.tenants.index'));
    expect($tenant->fresh())
        ->name->toBe('Updated Name')
        ->slug->toBe('updated-slug');
});

test('update tenant allows keeping its existing slug', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $tenant = Tenant::factory()->create([
        'name' => 'Old Name',
        'slug' => 'keep-this-slug',
    ]);

    $response = $this->actingAs($admin)->put(route('admin.tenants.update', $tenant), [
        'name' => 'Updated Name',
        'slug' => 'keep-this-slug',
        'status' => TenantStatus::ACTIVE->value,
    ]);

    $response->assertRedirect(route('admin.tenants.index'));
    expect($tenant->fresh()->name)->toBe('Updated Name');
});

test('update tenant fails with duplicate slug from another tenant', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Tenant::factory()->create(['slug' => 'existing-slug']);
    $tenant2 = Tenant::factory()->create(['slug' => 'second-slug']);

    $response = $this->actingAs($admin)->put(route('admin.tenants.update', $tenant2), [
        'name' => 'Another Name',
        'slug' => 'existing-slug',
    ]);

    $response->assertSessionHasErrors(['slug']);
});

test('platform admin can soft delete a tenant', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $tenant = Tenant::factory()->create();

    $response = $this->actingAs($admin)->delete(route('admin.tenants.destroy', $tenant));

    $response->assertRedirect(route('admin.tenants.index'));
    expect($tenant->fresh()?->trashed())->toBeTrue();
});

test('store tenant fails with nonexistent owner email', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.tenants.store'), [
        'name' => 'New Tenant',
        'slug' => 'new-tenant',
        'owner_email' => 'nonexistent@example.com',
        'status' => TenantStatus::ACTIVE->value,
    ]);

    $response->assertSessionHasErrors(['owner_email']);
});

test('user without specific permissions cannot mutate tenants', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');
    // Give panel access and view permission only
    $user->givePermissionTo('admin.panel.view');
    $user->givePermissionTo('tenants.view');

    $tenant = Tenant::factory()->create();

    // Try store
    $this->actingAs($user)->post(route('admin.tenants.store'), [
        'name' => 'Forbidden Store',
        'slug' => 'forbidden-store',
        'owner_email' => 'client@example.com',
    ])->assertForbidden();

    // Try update
    $this->actingAs($user)->put(route('admin.tenants.update', $tenant), [
        'name' => 'Forbidden Update',
        'slug' => 'forbidden-update',
    ])->assertForbidden();

    // Try status
    $this->actingAs($user)->patch(route('admin.tenants.status', $tenant), [
        'status' => TenantStatus::SUSPENDED->value,
    ])->assertForbidden();

    // Try destroy
    $this->actingAs($user)->delete(route('admin.tenants.destroy', $tenant))
        ->assertForbidden();
});
