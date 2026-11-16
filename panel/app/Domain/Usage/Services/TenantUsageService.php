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
use Illuminate\Support\Facades\Redis;

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

    /**
     * Record a playback view in Redis with 30-minute deduplication per video + client.
     */
    public function recordPlaybackView(string $tenantId, string $videoId, string $clientIdentifier): bool
    {
        $hash = md5("{$videoId}:{$clientIdentifier}");
        $dedupKey = "usage:view_dedup:{$hash}";

        // 30 minutes = 1800 seconds deduplication TTL
        $isNewView = (bool) Redis::set($dedupKey, '1', 'EX', 1800, 'NX');

        if (! $isNewView) {
            return false;
        }

        $date = now()->toDateString();
        Redis::incr("tenant:{$tenantId}:views:{$date}");

        return true;
    }

    /**
     * Record playback egress bytes in Redis atomically.
     */
    public function recordPlaybackEgress(string $tenantId, int $bytes): void
    {
        if ($bytes <= 0) {
            return;
        }

        $date = now()->toDateString();
        Redis::incrby("tenant:{$tenantId}:egress:{$date}", $bytes);
    }

    /**
     * Aggregate Redis playback counters into tenant_usage_records for active tenants.
     */
    public function aggregateHourlyUsage(?string $date = null): int
    {
        $targetDate = $date ?? now()->toDateString();
        $activeTenants = Tenant::query()->active()->get();
        $processed = 0;

        foreach ($activeTenants as $tenant) {
            $redisViews = Redis::get("tenant:{$tenant->id}:views:{$targetDate}");
            $redisEgress = Redis::get("tenant:{$tenant->id}:egress:{$targetDate}");

            $views = ($redisViews !== null && $redisViews !== false) ? (int) $redisViews : 0;
            $egress = ($redisEgress !== null && $redisEgress !== false) ? (int) $redisEgress : 0;

            $storageBytes = $this->getCurrentStorageBytes($tenant);
            $videoCount = Video::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();

            TenantUsageRecord::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'date' => $targetDate,
                ],
                [
                    'storage_bytes' => $storageBytes,
                    'egress_bytes' => $egress,
                    'view_count' => $views,
                    'video_count' => $videoCount,
                ]
            );

            $processed++;
        }

        return $processed;
    }
}
