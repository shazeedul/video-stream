<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Tenants\Models\Tenant;
use App\Models\User;

final class TenantPolicy
{
    /**
     * Determine whether the user can view any tenants.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('tenants.view');
    }

    /**
     * Determine whether the user can view the tenant.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.view')
            || $user->isTenantMember($tenant);
    }

    /**
     * Determine whether the user can create tenants.
     */
    public function create(User $user): bool
    {
        return $user->can('tenants.create') || $user->exists;
    }

    /**
     * Determine whether the user can update the tenant.
     */
    public function update(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.update')
            || (bool) $user->tenantRole($tenant)?->isOwner()
            || (bool) $user->tenantRole($tenant)?->isAdmin();
    }

    /**
     * Determine whether the user can delete the tenant.
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.delete')
            || (bool) $user->tenantRole($tenant)?->isOwner();
    }

    /**
     * Determine whether the user can manage tenant members.
     */
    public function manageMembers(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.update')
            || $user->hasRole('admin')
            || ($user->tenantRole($tenant)?->canManageMembers() ?? false);
    }

    /**
     * Determine whether the user can suspend the tenant.
     */
    public function suspend(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.suspend');
    }

    /**
     * Determine whether the user can restore the tenant.
     */
    public function restore(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.delete')
            || (bool) $user->tenantRole($tenant)?->isOwner();
    }

    /**
     * Determine whether the user can permanently delete the tenant.
     */
    public function forceDelete(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.delete');
    }
}
