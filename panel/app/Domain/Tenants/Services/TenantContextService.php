<?php

declare(strict_types=1);

namespace App\Domain\Tenants\Services;

use App\Domain\Tenants\Models\Tenant;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Enums\TenantUserStatus;
use App\Models\User;
use Illuminate\Support\Str;

final class TenantContextService
{
    /**
     * Resolve the current tenant for the given user.
     */
    public function getCurrentTenant(User $user): ?Tenant
    {
        $sessionTenantId = session('current_tenant_id');

        if (is_string($sessionTenantId) && $sessionTenantId !== '') {
            /** @var Tenant|null $sessionTenant */
            $sessionTenant = Tenant::query()->find($sessionTenantId);

            if ($sessionTenant !== null && ! $sessionTenant->trashed() && $user->isTenantMember($sessionTenant)) {
                return $sessionTenant;
            }

            session()->forget('current_tenant_id');
        }

        // Fallback: load user's first active tenant
        /** @var Tenant|null $activeTenant */
        $activeTenant = $user->tenants()
            ->where('tenants.status', TenantStatus::ACTIVE)
            ->first();

        if ($activeTenant !== null) {
            session(['current_tenant_id' => $activeTenant->id]);

            return $activeTenant;
        }

        // If the user has other tenant memberships (e.g. suspended), fallback to the first tenant
        /** @var Tenant|null $fallbackTenant */
        $fallbackTenant = $user->tenants()->first();

        if ($fallbackTenant !== null) {
            session(['current_tenant_id' => $fallbackTenant->id]);

            return $fallbackTenant;
        }

        // Review Focus 1: Orphaned user without tenants -> auto-provision default organization
        $name = ($user->name !== null && mb_trim($user->name) !== '') ? mb_trim($user->name) : 'Personal';
        $slug = Str::slug("{$name}-team-".Str::lower(Str::random(5)));

        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = Str::slug("{$name}-team-".Str::lower(Str::random(5)));
        }

        $tenant = Tenant::query()->create([
            'name' => "{$name}'s Team",
            'slug' => $slug,
            'status' => TenantStatus::ACTIVE,
        ]);

        $tenant->users()->attach($user->id, [
            'id' => (string) Str::uuid(),
            'role' => TenantRole::OWNER->value,
            'status' => TenantUserStatus::ACTIVE->value,
        ]);

        $user->unsetRelation('tenants');
        session(['current_tenant_id' => $tenant->id]);

        return $tenant;
    }

    /**
     * Set the current tenant for the user in session.
     */
    public function setCurrentTenant(User $user, Tenant $tenant): bool
    {
        if ($tenant->trashed() || ! $user->isTenantMember($tenant)) {
            return false;
        }

        session(['current_tenant_id' => $tenant->id]);

        return true;
    }

    /**
     * Clear the current tenant from session.
     */
    public function clearCurrentTenant(): void
    {
        session()->forget('current_tenant_id');
    }
}
