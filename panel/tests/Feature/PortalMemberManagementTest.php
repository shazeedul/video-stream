<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Enums\TenantRole;
use App\Models\User;

test('owner can add an existing user as member to the organization', function (): void {
    $owner = User::factory()->create();
    $newUser = User::factory()->create(['email' => 'worker@example.com']);
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($owner->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);

    session(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($owner)->post(route('portal.settings.organization.members.store'), [
        'email' => 'worker@example.com',
        'role' => TenantRole::MEMBER->value,
    ]);

    $response->assertRedirect();
    expect($tenant->users()->where('users.id', $newUser->id)->exists())->toBeTrue();
});

// Review Focus 5: Duplicate Membership Invitation
test('adding an already existing member fails with validation error', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create(['email' => 'existing@example.com']);
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($owner->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);
    $tenant->users()->attach($member->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    session(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($owner)->post(route('portal.settings.organization.members.store'), [
        'email' => 'existing@example.com',
        'role' => TenantRole::MEMBER->value,
    ]);

    $response->assertSessionHasErrors('email');
});

// Review Focus 2: Owner Removal / Demotion Deadlock
test('sole owner cannot remove themselves from the organization', function (): void {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($owner->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);
    $pivot = $tenant->memberships()->where('user_id', $owner->id)->first();

    session(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($owner)->delete(route('portal.settings.organization.members.destroy', $pivot));

    $response->assertStatus(422);
    expect($tenant->users()->count())->toBe(1);
});

test('sole owner cannot demote their role to member', function (): void {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($owner->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);
    $pivot = $tenant->memberships()->where('user_id', $owner->id)->first();

    session(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($owner)->patch(route('portal.settings.organization.members.update', $pivot), [
        'role' => TenantRole::MEMBER->value,
    ]);

    $response->assertStatus(422);
    expect($pivot->fresh()->role)->toBe(TenantRole::OWNER);
});

test('owner can demote an owner when another owner exists', function (): void {
    $owner1 = User::factory()->create();
    $owner2 = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($owner1->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);
    $tenant->users()->attach($owner2->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);
    $pivot2 = $tenant->memberships()->where('user_id', $owner2->id)->first();

    session(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($owner1)->patch(route('portal.settings.organization.members.update', $pivot2), [
        'role' => TenantRole::MEMBER->value,
    ]);

    $response->assertRedirect();
    expect($pivot2->fresh()->role)->toBe(TenantRole::MEMBER);
});

test('owner can remove another owner when another owner exists', function (): void {
    $owner1 = User::factory()->create();
    $owner2 = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($owner1->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);
    $tenant->users()->attach($owner2->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);
    $pivot2 = $tenant->memberships()->where('user_id', $owner2->id)->first();

    session(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($owner1)->delete(route('portal.settings.organization.members.destroy', $pivot2));

    $response->assertRedirect();
    expect($tenant->users()->where('users.id', $owner2->id)->exists())->toBeFalse();
});

test('owner can remove a regular member from the organization', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($owner->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);
    $tenant->users()->attach($member->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);
    $pivotMember = $tenant->memberships()->where('user_id', $member->id)->first();

    session(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($owner)->delete(route('portal.settings.organization.members.destroy', $pivotMember));

    $response->assertRedirect();
    expect($tenant->users()->where('users.id', $member->id)->exists())->toBeFalse();
});

test('regular member cannot add, update, or remove members', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $other = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($owner->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);
    $tenant->users()->attach($member->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);
    $tenant->users()->attach($other->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);
    $pivotOther = $tenant->memberships()->where('user_id', $other->id)->first();

    session(['current_tenant_id' => $tenant->id]);

    $candidate = User::factory()->create(['email' => 'candidate@example.com']);

    $this->actingAs($member)
        ->post(route('portal.settings.organization.members.store'), [
            'email' => 'candidate@example.com',
            'role' => TenantRole::MEMBER->value,
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->patch(route('portal.settings.organization.members.update', $pivotOther), [
            'role' => TenantRole::ADMIN->value,
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('portal.settings.organization.members.destroy', $pivotOther))
        ->assertForbidden();
});

test('cannot modify or remove membership from a different organization', function (): void {
    $owner = User::factory()->create();
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userB = User::factory()->create();

    $tenantA->users()->attach($owner->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);
    $tenantB->users()->attach($userB->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $pivotB = $tenantB->memberships()->where('user_id', $userB->id)->first();

    session(['current_tenant_id' => $tenantA->id]);

    $this->actingAs($owner)
        ->patch(route('portal.settings.organization.members.update', $pivotB), [
            'role' => TenantRole::ADMIN->value,
        ])
        ->assertNotFound();

    $this->actingAs($owner)
        ->delete(route('portal.settings.organization.members.destroy', $pivotB))
        ->assertNotFound();
});

test('authorized user can view organization members list', function (): void {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($owner->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);

    session(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($owner)->get(route('portal.settings.organization.members.index'));

    $response->assertOk();
});
