<?php

declare(strict_types=1);

namespace App\Domain\Tenants\Services;

use App\Domain\Tenants\Contracts\TenantContextServiceInterface;
use App\Domain\Tenants\Models\Tenant;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Enums\TenantUserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TenantContextService implements TenantContextServiceInterface
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
                if ($sessionTenant->status->isSuspended()) {
                    /** @var Tenant|null $activeTenant */
                    $activeTenant = $user->tenants()
                        ->where('tenants.id', '!=', $sessionTenant->id)
                        ->where('tenants.status', TenantStatus::ACTIVE)
                        ->first();

                    if ($activeTenant !== null) {
                        session(['current_tenant_id' => $activeTenant->id]);

                        return $activeTenant;
                    }
                }

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

        $tenant = DB::transaction(function () use ($user, $name, $slug): Tenant {
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

            return $tenant;
        });

        $user->unsetRelation('tenants');
        session(['current_tenant_id' => $tenant->id]);

        return $tenant;
    }

    /**
     * Get the active tenant from container instance or resolve for user.
     */
    public function getActiveTenant(?User $user = null): ?Tenant
    {
        if (app()->bound('current_tenant')) {
            /** @var mixed $tenant */
            $tenant = app('current_tenant');
            if ($tenant instanceof Tenant) {
                return $tenant;
            }
        }

        if ($user !== null) {
            return $this->getCurrentTenant($user);
        }

        return null;
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
