<?php

declare(strict_types=1);

namespace Tests\Feature\Monitoring;

use App\Domain\Playback\Contracts\PlaybackTokenServiceInterface;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Usage\Contracts\TenantUsageServiceInterface;
use App\Domain\Usage\Models\TenantUsageRecord;
use App\Domain\Videos\Models\Video;
use App\Domain\Videos\Models\VideoAsset;
use App\Enums\AssetType;
use App\Enums\TenantRole;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EndToEndSystemReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Redis::del('workers:active');
        Redis::del('worker:heartbeat:worker-e2e-readiness');
    }

    protected function tearDown(): void
    {
        Redis::del('workers:active');
        Redis::del('worker:heartbeat:worker-e2e-readiness');

        parent::tearDown();
    }

    public function test_liveness_probe_returns_200_ok(): void
    {
        Redis::sadd('workers:active', 'worker-e2e-readiness');
        Redis::setex('worker:heartbeat:worker-e2e-readiness', 45, (string) json_encode([
            'id' => 'worker-e2e-readiness',
            'hostname' => 'e2e-node-1',
            'timestamp' => time(),
        ], JSON_THROW_ON_ERROR));

        $response = $this->get('/health');

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'database' => 'ok',
                'redis' => 'ok',
            ]);
    }

    public function test_deep_admin_diagnostics_returns_component_latencies_and_worker_health(): void
    {
        Redis::sadd('workers:active', 'worker-e2e-readiness');
        Redis::setex('worker:heartbeat:worker-e2e-readiness', 45, (string) json_encode([
            'id' => 'worker-e2e-readiness',
            'hostname' => 'e2e-node-1',
            'timestamp' => time(),
        ], JSON_THROW_ON_ERROR));

        $admin = User::factory()->admin()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/health');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'timestamp',
                'components' => [
                    'database' => ['status', 'latency_ms'],
                    'redis' => ['status', 'latency_ms'],
                    'minio' => ['status', 'buckets', 'latency_ms'],
                    'rabbitmq' => ['status', 'queue_depth', 'consumers', 'latency_ms'],
                    'workers' => ['status', 'active_count', 'instances'],
                ],
            ]);

        $data = $response->json();
        $this->assertContains('videos-original', $data['components']['minio']['buckets']);
        $this->assertContains('videos-processed', $data['components']['minio']['buckets']);
        $this->assertContains('thumbnails', $data['components']['minio']['buckets']);
        $this->assertGreaterThanOrEqual(1, $data['components']['workers']['active_count']);
    }

    public function test_creating_video_with_assets_increases_storage_and_upload_beyond_quota_returns_422(): void
    {
        $tenant = Tenant::factory()->create([
            'settings' => ['storage_quota_bytes' => 10 * 1024 * 1024], // 10 MB quota
        ]);

        $user = User::factory()->create();
        $user->assignRole('user');
        $tenant->users()->attach($user->id, [
            'id' => (string) str()->uuid(),
            'role' => TenantRole::MEMBER->value,
        ]);

        /** @var TenantUsageServiceInterface $usageService */
        $usageService = app(TenantUsageServiceInterface::class);
        $this->assertSame(0, $usageService->getCurrentStorageBytes($tenant));

        $video = Video::factory()->forTenant($tenant)->create([
            'user_id' => $user->id,
            'status' => VideoStatus::DRAFT,
        ]);

        // Add 6 MB of assets
        VideoAsset::factory()->create([
            'video_id' => $video->id,
            'type' => AssetType::ORIGINAL,
            'size_bytes' => 6 * 1024 * 1024,
        ]);

        $this->assertSame(6 * 1024 * 1024, $usageService->getCurrentStorageBytes($tenant));

        // Attempt to initiate upload with 5 MB additional (6MB + 5MB = 11MB > 10MB quota) -> 422
        $response = $this->actingAs($user)
            ->postJson(route('portal.videos.upload.initiate', $video), [
                'filename' => 'chunk_over_quota.mp4',
                'file_size' => 5 * 1024 * 1024,
                'content_type' => 'video/mp4',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Tenant storage quota exceeded. Please upgrade your plan or remove existing media.',
            ]);
    }

    public function test_playback_authorization_subrequest_records_view_and_increments_egress(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $video = Video::factory()
            ->forTenant($tenant)
            ->ready()
            ->create([
                'user_id' => $user->id,
                'visibility' => VideoVisibility::PUBLIC,
            ]);

        $date = now()->toDateString();
        Redis::del("tenant:{$tenant->id}:views:{$date}");
        Redis::del("tenant:{$tenant->id}:egress:{$date}");

        /** @var PlaybackTokenServiceInterface $tokenService */
        $tokenService = app(PlaybackTokenServiceInterface::class);
        $expires = time() + 3600;
        $token = $tokenService->generateToken((string) $video->id, $expires);

        // 1. Master manifest authorization subrequest -> records playback view
        $masterAuthResponse = $this->withHeaders([
            'X-Original-URI' => "/playback/{$video->id}/master.m3u8",
            'X-Video-Id' => (string) $video->id,
        ])->get("/api/v1/internal/playback/auth?video_id={$video->id}&token={$token}&expires={$expires}");

        $masterAuthResponse->assertOk()
            ->assertHeader('X-Tenant-Id', (string) $tenant->id);

        $views = (int) Redis::get("tenant:{$tenant->id}:views:{$date}");
        $this->assertSame(1, $views);

        // 2. Video segment chunk authorization subrequest -> increments egress bytes
        $segmentAuthResponse = $this->withHeaders([
            'X-Original-URI' => "/playback/{$video->id}/segment_000.ts",
            'X-Video-Id' => (string) $video->id,
        ])->get("/api/v1/internal/playback/auth?video_id={$video->id}&token={$token}&expires={$expires}");

        $segmentAuthResponse->assertOk()
            ->assertHeader('X-Tenant-Id', (string) $tenant->id);

        $egress = (int) Redis::get("tenant:{$tenant->id}:egress:{$date}");
        $this->assertSame(1572864, $egress);
    }

    public function test_aggregation_command_persists_metrics_into_tenant_usage_records(): void
    {
        $tenant = Tenant::factory()->create();
        $date = now()->toDateString();

        Redis::set("tenant:{$tenant->id}:views:{$date}", 15);
        Redis::set("tenant:{$tenant->id}:egress:{$date}", 3145728);

        $this->artisan('usage:aggregate-bandwidth')
            ->assertSuccessful();

        $this->assertDatabaseHas('tenant_usage_records', [
            'tenant_id' => $tenant->id,
            'date' => $date,
            'view_count' => 15,
            'egress_bytes' => 3145728,
        ]);
    }

    public function test_admin_monitoring_and_portal_usage_pages_render_with_aggregated_data(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Readiness Media Corp',
            'settings' => ['storage_quota_bytes' => 20 * 1024 * 1024 * 1024],
        ]);

        $user = User::factory()->create();
        $user->assignRole('user');
        $tenant->users()->attach($user->id, [
            'id' => (string) str()->uuid(),
            'role' => TenantRole::OWNER->value,
        ]);

        $video = Video::factory()->forTenant($tenant)->ready()->create([
            'user_id' => $user->id,
        ]);

        VideoAsset::factory()->create([
            'video_id' => $video->id,
            'type' => AssetType::ORIGINAL,
            'size_bytes' => 50_000_000,
        ]);

        $date = now()->toDateString();
        TenantUsageRecord::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'date' => $date,
            'storage_bytes' => 50_000_000,
            'egress_bytes' => 10_000_000,
            'view_count' => 120,
            'video_count' => 1,
        ]);

        // 1. Portal Usage Page render verification
        $this->actingAs($user)
            ->get(route('portal.settings.usage'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/settings/usage')
                ->has('storage')
                ->has('breakdown')
                ->has('trends')
                ->where('storage.used_bytes', 50_000_000)
            );

        // 2. Admin System Monitoring Page render verification
        $admin = User::factory()->admin()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.monitoring.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/monitoring/index')
                ->has('diagnostics')
                ->has('queue')
                ->has('topTenants')
            );
    }
}
