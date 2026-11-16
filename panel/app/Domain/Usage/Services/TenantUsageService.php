<?php

declare(strict_types=1);

namespace App\Domain\Usage\Services;

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Uploads\Models\UploadSession;
use App\Domain\Usage\Contracts\TenantUsageServiceInterface;
use App\Domain\Usage\Models\TenantUsageRecord;
use App\Domain\Videos\Models\Video;
use App\Domain\Videos\Models\VideoAsset;
use App\Enums\UploadSessionStatus;

final class TenantUsageService implements TenantUsageServiceInterface
{
    /**
     * Calculate current storage bytes used by the tenant.
     * Sums size_bytes of VideoAsset records and estimated bytes of in-flight UploadSessions.
     */
    public function getCurrentStorageBytes(Tenant $tenant): int
    {
        $videoIds = Video::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->select('id');

        $assetStorage = (int) VideoAsset::query()
            ->whereIn('video_id', $videoIds)
            ->sum('size_bytes');

        $uploadStorage = (int) UploadSession::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [
                UploadSessionStatus::PENDING->value,
                UploadSessionStatus::UPLOADING->value,
                'pending',
                'uploading',
                'PENDING',
                'UPLOADING',
            ])
            ->selectRaw('COALESCE(SUM(part_size * total_parts), 0) as total')
            ->value('total');

        return $assetStorage + $uploadStorage;
    }

    /**
     * Get the tenant's configured or default storage quota in bytes.
     */
    public function getStorageQuotaBytes(Tenant $tenant): int
    {
        return $tenant->getStorageQuotaBytes();
    }

    /**
     * Determine whether the tenant has exceeded their storage quota.
     */
    public function hasExceededStorageQuota(Tenant $tenant, int $additionalBytes = 0): bool
    {
        $currentStorage = $this->getCurrentStorageBytes($tenant);
        $quota = $this->getStorageQuotaBytes($tenant);

        return ($currentStorage + $additionalBytes) > $quota;
    }

    /**
     * Recalculate and persist the current storage for a tenant in today's TenantUsageRecord.
     */
    public function recalculateTenantStorage(Tenant $tenant): int
    {
        $currentStorage = $this->getCurrentStorageBytes($tenant);
        $videoCount = Video::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();

        TenantUsageRecord::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'date' => now()->toDateString(),
            ],
            [
                'storage_bytes' => $currentStorage,
                'video_count' => $videoCount,
            ]
        );

        return $currentStorage;
    }
}
