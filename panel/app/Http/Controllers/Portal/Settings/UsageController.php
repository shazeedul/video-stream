<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal\Settings;

use App\Domain\Tenants\Contracts\TenantContextServiceInterface;
use App\Domain\Usage\Contracts\TenantUsageServiceInterface;
use App\Domain\Usage\Models\TenantUsageRecord;
use App\Domain\Videos\Models\Video;
use App\Domain\Videos\Models\VideoAsset;
use App\Enums\AssetType;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class UsageController extends Controller
{
    public function __construct(
        private readonly TenantUsageServiceInterface $tenantUsageService,
        private readonly TenantContextServiceInterface $tenantContextService,
    ) {}

    /**
     * Display the tenant storage usage and bandwidth metrics dashboard.
     */
    public function index(Request $request): Response
    {
        $tenant = $this->tenantContextService->getActiveTenant($request->user());

        if ($tenant === null) {
            abort(404, __('Organization not found.'));
        }

        $currentStorage = $this->tenantUsageService->getCurrentStorageBytes($tenant);
        $storageQuota = $this->tenantUsageService->getStorageQuotaBytes($tenant);
        $usagePercentage = $storageQuota > 0 ? round(($currentStorage / $storageQuota) * 100, 2) : 0.0;

        $breakdownRecords = VideoAsset::query()
            ->whereIn(
                'video_id',
                Video::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->select('id')
            )
            ->selectRaw('type, COALESCE(SUM(size_bytes), 0) as total_bytes, COUNT(*) as asset_count')
            ->groupBy('type')
            ->get();

        $originals = 0;
        $originalsCount = 0;
        $hls = 0;
        $hlsCount = 0;
        $thumbnails = 0;
        $thumbnailsCount = 0;

        foreach ($breakdownRecords as $record) {
            $rawType = $record->type instanceof AssetType ? $record->type->value : (string) $record->type;
            $typeStr = mb_strtolower($rawType);
            $bytes = (int) $record->total_bytes;
            $count = (int) $record->asset_count;

            if ($typeStr === 'original') {
                $originals += $bytes;
                $originalsCount += $count;
            } elseif (in_array($typeStr, ['hls_playlist', 'hls_segment', 'master', 'hls_variant', 'hls'], true)) {
                $hls += $bytes;
                $hlsCount += $count;
            } elseif ($typeStr === 'thumbnail') {
                $thumbnails += $bytes;
                $thumbnailsCount += $count;
            }
        }

        $records = TenantUsageRecord::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->orderBy('date', 'asc')
            ->get(['date', 'storage_bytes', 'egress_bytes', 'view_count', 'video_count'])
            ->map(fn (TenantUsageRecord $r): array => [
                'date' => $r->date->toDateString(),
                'storage_bytes' => (int) $r->storage_bytes,
                'egress_bytes' => (int) $r->egress_bytes,
                'view_count' => (int) $r->view_count,
                'video_count' => (int) $r->video_count,
                'formatted_egress' => $this->formatBytes((int) $r->egress_bytes),
                'formatted_storage' => $this->formatBytes((int) $r->storage_bytes),
            ]);

        $totalViews = (int) $records->sum('view_count');
        $totalEgress = (int) $records->sum('egress_bytes');

        return Inertia::render('portal/settings/usage', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'storage' => [
                'used_bytes' => $currentStorage,
                'quota_bytes' => $storageQuota,
                'usage_percentage' => $usagePercentage,
                'formatted_used' => $this->formatBytes($currentStorage),
                'formatted_quota' => $this->formatBytes($storageQuota),
                'is_exceeded' => $currentStorage > $storageQuota,
            ],
            'quota' => [
                'used_bytes' => $currentStorage,
                'quota_bytes' => $storageQuota,
                'usage_percentage' => $usagePercentage,
            ],
            'breakdown' => [
                'original' => [
                    'bytes' => $originals,
                    'count' => $originalsCount,
                    'formatted' => $this->formatBytes($originals),
                    'percentage' => $currentStorage > 0 ? round(($originals / $currentStorage) * 100, 1) : 0.0,
                ],
                'hls' => [
                    'bytes' => $hls,
                    'count' => $hlsCount,
                    'formatted' => $this->formatBytes($hls),
                    'percentage' => $currentStorage > 0 ? round(($hls / $currentStorage) * 100, 1) : 0.0,
                ],
                'thumbnail' => [
                    'bytes' => $thumbnails,
                    'count' => $thumbnailsCount,
                    'formatted' => $this->formatBytes($thumbnails),
                    'percentage' => $currentStorage > 0 ? round(($thumbnails / $currentStorage) * 100, 1) : 0.0,
                ],
            ],
            'trends' => [
                'total_views_30d' => $totalViews,
                'total_egress_bytes_30d' => $totalEgress,
                'formatted_egress_30d' => $this->formatBytes($totalEgress),
                'records' => $records,
            ],
            'records' => $records,
        ]);
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $power = (int) floor(log($bytes, 1024));
        $power = max(0, min($power, count($units) - 1));
        $value = $bytes / (1024 ** $power);

        return round($value, $precision).' '.$units[$power];
    }
}
