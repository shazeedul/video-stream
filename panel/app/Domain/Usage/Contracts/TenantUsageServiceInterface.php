<?php

declare(strict_types=1);

namespace App\Domain\Usage\Contracts;

use App\Domain\Tenants\Models\Tenant;

interface TenantUsageServiceInterface
{
    public const int DEFAULT_SEGMENT_ESTIMATE_BYTES = 1_572_864;

    public function getCurrentStorageBytes(Tenant $tenant): int;

    public function getStorageQuotaBytes(Tenant $tenant): int;

    public function hasExceededStorageQuota(Tenant $tenant, int $additionalBytes = 0): bool;

    public function recalculateTenantStorage(Tenant $tenant): int;

    public function recordPlaybackView(string $tenantId, string $videoId, string $clientIdentifier): bool;

    public function recordPlaybackEgress(string $tenantId, int $bytes): void;

    public function aggregateHourlyUsage(?string $date = null): int;
}
