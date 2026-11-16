<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Monitoring\Contracts\HealthCheckServiceInterface;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\VideoAsset;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class MonitoringController extends Controller
{
    public function __construct(
        private readonly HealthCheckServiceInterface $healthCheckService,
    ) {}

    /**
     * Display the infrastructure health monitoring dashboard.
     */
    public function index(): Response
    {
        $diagnostics = $this->healthCheckService->checkDiagnostics();
        $components = $diagnostics->components;

        $topTenants = Tenant::query()
            ->addSelect([
                'storage_bytes' => VideoAsset::query()
                    ->join('videos', 'videos.id', '=', 'video_assets.video_id')
                    ->withoutGlobalScopes()
                    ->whereColumn('videos.tenant_id', 'tenants.id')
                    ->selectRaw('COALESCE(SUM(video_assets.size_bytes), 0)'),
            ])
            ->orderByDesc('storage_bytes')
            ->limit(10)
            ->get()
            ->map(function (Tenant $tenant): array {
                $storage = (int) $tenant->getAttribute('storage_bytes');
                $quota = $tenant->getStorageQuotaBytes();
                $usagePercentage = $quota > 0 ? round(($storage / $quota) * 100, 2) : 0.0;

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'storage_bytes' => $storage,
                    'quota_bytes' => $quota,
                    'usage_percentage' => $usagePercentage,
                    'formatted_storage' => $this->formatBytes($storage),
                    'formatted_quota' => $this->formatBytes($quota),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('admin/monitoring/index', [
            'diagnostics' => $diagnostics->toArray(),
            'health' => $diagnostics->toArray(),
            'components' => $components,
            'queue' => $components['rabbitmq'] ?? [
                'status' => 'unknown',
                'queue_depth' => 0,
                'consumers' => 0,
                'latency_ms' => 0.0,
            ],
            'workers' => $components['workers'] ?? [
                'status' => 'unknown',
                'active_count' => 0,
                'instances' => [],
            ],
            'topTenants' => $topTenants,
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
