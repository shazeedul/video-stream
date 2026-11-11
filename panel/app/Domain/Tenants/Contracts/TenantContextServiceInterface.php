<?php

declare(strict_types=1);

namespace App\Domain\Tenants\Contracts;

use App\Domain\Tenants\Models\Tenant;
use App\Models\User;

interface TenantContextServiceInterface
{
    /**
     * Resolve the current tenant for the given user.
     */
    public function getCurrentTenant(User $user): ?Tenant;

    /**
     * Get the active tenant from container instance or resolve for user.
     */
    public function getActiveTenant(?User $user = null): ?Tenant;

    /**
     * Set the current tenant for the user in session.
     */
    public function setCurrentTenant(User $user, Tenant $tenant): bool;

    /**
     * Clear the current tenant from session.
     */
    public function clearCurrentTenant(): void;
}
