<?php

declare(strict_types=1);

namespace App\Domain\Usage\Contracts;

use App\Domain\Tenants\Models\Tenant;

interface TenantUsageServiceInterface
{
    public function getCurrentStorageBytes(Tenant $tenant): int;

    public function getStorageQuotaBytes(Tenant $tenant): int;

    public function hasExceededStorageQuota(Tenant $tenant, int $additionalBytes = 0): bool;

    public function recalculateTenantStorage(Tenant $tenant): int;
}
