<?php

declare(strict_types=1);

use App\Domain\Monitoring\Contracts\HealthCheckServiceInterface;
use App\Domain\Monitoring\DataTransferObjects\HealthReport;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\Video;
use App\Domain\Videos\Models\VideoAsset;
use App\Enums\AssetType;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();

    $mockHealth = Mockery::mock(HealthCheckServiceInterface::class);
    $mockHealth->shouldReceive('checkDiagnostics')->byDefault()->andReturn(
        new HealthReport(
            status: 'healthy',
            timestamp: now()->toIso8601String(),
            components: [
                'database' => ['status' => 'ok', 'latency_ms' => 1.5],
                'redis' => ['status' => 'ok', 'latency_ms' => 0.8],
                'minio' => ['status' => 'ok', 'buckets' => ['videos-original', 'videos-processed', 'thumbnails'], 'latency_ms' => 12.4],
                'rabbitmq' => ['status' => 'ok', 'queue_depth' => 4, 'consumers' => 2, 'latency_ms' => 3.2],
                'workers' => [
                    'status' => 'ok',
                    'active_count' => 1,
                    'instances' => [
                        ['id' => 'worker-node-1', 'hostname' => 'worker-node-1', 'age_seconds' => 5],
                    ],
                ],
            ]
        )
    );
    app()->instance(HealthCheckServiceInterface::class, $mockHealth);
});

it('displays admin system monitoring dashboard for admin user', function (): void {
    $admin = adminUser();

    actingAs($admin)
        ->get(route('admin.monitoring.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/monitoring/index')
            ->has('diagnostics')
            ->has('queue')
            ->has('topTenants')
            ->where('diagnostics.status', 'healthy')
            ->where('queue.queue_depth', 4)
        );
});

it('ranks top tenants by storage bytes with quota comparison', function (): void {
    $admin = adminUser();

    $tenantSmall = Tenant::factory()->create([
        'name' => 'Small Tenant',
        'settings' => ['storage_quota_bytes' => 10 * 1024 * 1024 * 1024],
    ]);
    $tenantLarge = Tenant::factory()->create([
        'name' => 'Large Tenant',
        'settings' => ['storage_quota_bytes' => 50 * 1024 * 1024 * 1024],
    ]);

    $videoSmall = Video::factory()->forTenant($tenantSmall)->create();
    VideoAsset::factory()->create([
        'video_id' => $videoSmall->id,
        'type' => AssetType::ORIGINAL,
        'size_bytes' => 50_000_000,
    ]);

    $videoLarge = Video::factory()->forTenant($tenantLarge)->create();
    VideoAsset::factory()->create([
        'video_id' => $videoLarge->id,
        'type' => AssetType::ORIGINAL,
        'size_bytes' => 300_000_000,
    ]);

    actingAs($admin)
        ->get(route('admin.monitoring.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/monitoring/index')
            ->has('topTenants', 2)
            ->where('topTenants.0.name', 'Large Tenant')
            ->where('topTenants.0.storage_bytes', 300_000_000)
            ->where('topTenants.1.name', 'Small Tenant')
            ->where('topTenants.1.storage_bytes', 50_000_000)
        );
});

it('denies access to monitoring for portal users', function (): void {
    $user = portalUser();

    actingAs($user)
        ->get(route('admin.monitoring.index'))
        ->assertRedirect();
});

it('denies access to monitoring for users without system-settings.view permission', function (): void {
    $user = userWithPermissions(['admin.panel.view']);

    actingAs($user)
        ->get(route('admin.monitoring.index'))
        ->assertForbidden();
});

it('redirects unauthenticated users to login', function (): void {
    test()
        ->get(route('admin.monitoring.index'))
        ->assertRedirect(route('login'));
});
