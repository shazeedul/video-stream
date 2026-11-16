<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Internal;

use App\Domain\Playback\Contracts\PlaybackTokenServiceInterface;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\Video;
use App\Enums\VideoStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

final class PlaybackUsageTrackingTest extends TestCase
{
    use RefreshDatabase;

    private PlaybackTokenServiceInterface $tokenService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenService = app(PlaybackTokenServiceInterface::class);
        Redis::connection()->flushdb();
    }

    protected function tearDown(): void
    {
        Redis::connection()->flushdb();
        parent::tearDown();
    }

    public function test_requesting_master_m3u8_records_one_view_in_redis(): void
    {
        $tenant = Tenant::factory()->create();
        $video = Video::factory()->forTenant($tenant)->ready()->create();

        $expiresAt = time() + 3600;
        $token = $this->tokenService->generateToken((string) $video->id, $expiresAt);

        $response = $this->withHeaders([
            'X-Original-URI' => "/playback/{$video->id}/master.m3u8",
        ])->get("/api/v1/internal/playback/auth?video_id={$video->id}&token={$token}&expires={$expiresAt}");

        $response->assertOk();
        $response->assertHeader('X-Tenant-Id', $tenant->id);

        $date = now()->toDateString();
        $views = (int) Redis::get("tenant:{$tenant->id}:views:{$date}");
        $this->assertSame(1, $views);
    }

    public function test_repeated_requests_for_master_m3u8_with_same_token_within_30_minutes_does_not_increment_view_count_again(): void
    {
        $tenant = Tenant::factory()->create();
        $video = Video::factory()->forTenant($tenant)->ready()->create();

        $expiresAt = time() + 3600;
        $token = $this->tokenService->generateToken((string) $video->id, $expiresAt);

        // First request - should increment view count
        $response1 = $this->withHeaders([
            'X-Original-URI' => "/playback/{$video->id}/master.m3u8",
        ])->get("/api/v1/internal/playback/auth?video_id={$video->id}&token={$token}&expires={$expiresAt}");

        $response1->assertOk();

        $date = now()->toDateString();
        $this->assertSame(1, (int) Redis::get("tenant:{$tenant->id}:views:{$date}"));

        // Second request with same token within 30 min window - should NOT increment
        $response2 = $this->withHeaders([
            'X-Original-URI' => "/playback/{$video->id}/master.m3u8",
        ])->get("/api/v1/internal/playback/auth?video_id={$video->id}&token={$token}&expires={$expiresAt}");

        $response2->assertOk();
        $this->assertSame(1, (int) Redis::get("tenant:{$tenant->id}:views:{$date}"));
    }

    public function test_requesting_ts_segment_records_egress_bytes_in_redis(): void
    {
        $tenant = Tenant::factory()->create();
        $video = Video::factory()->forTenant($tenant)->ready()->create();

        $expiresAt = time() + 3600;
        $token = $this->tokenService->generateToken((string) $video->id, $expiresAt);

        // Request first segment
        $response1 = $this->withHeaders([
            'X-Original-URI' => "/playback/{$video->id}/720p/segment_00001.ts",
        ])->get("/api/v1/internal/playback/auth?video_id={$video->id}&token={$token}&expires={$expiresAt}");

        $response1->assertOk();

        $date = now()->toDateString();
        $this->assertSame(1572864, (int) Redis::get("tenant:{$tenant->id}:egress:{$date}"));

        // Request second segment with query parameters in X-Original-URI
        $response2 = $this->withHeaders([
            'X-Original-URI' => "/playback/{$video->id}/720p/segment_00002.ts?token={$token}&expires={$expiresAt}",
        ])->get("/api/v1/internal/playback/auth?video_id={$video->id}&token={$token}&expires={$expiresAt}");

        $response2->assertOk();
        $this->assertSame(3145728, (int) Redis::get("tenant:{$tenant->id}:egress:{$date}"));
    }

    public function test_non_ready_video_or_forbidden_request_does_not_increment_views_or_egress(): void
    {
        $tenant = Tenant::factory()->create();
        $video = Video::factory()->forTenant($tenant)->create([
            'status' => VideoStatus::TRANSCODING,
        ]);

        $expiresAt = time() + 3600;
        $token = $this->tokenService->generateToken((string) $video->id, $expiresAt);

        // Requesting master.m3u8 on transcoding video
        $response1 = $this->withHeaders([
            'X-Original-URI' => "/playback/{$video->id}/master.m3u8",
        ])->get("/api/v1/internal/playback/auth?video_id={$video->id}&token={$token}&expires={$expiresAt}");

        $response1->assertStatus(403);

        $date = now()->toDateString();
        $this->assertSame(0, (int) Redis::get("tenant:{$tenant->id}:views:{$date}"));

        // Requesting .ts segment with invalid token on ready video
        $readyVideo = Video::factory()->forTenant($tenant)->ready()->create();

        $response2 = $this->withHeaders([
            'X-Original-URI' => "/playback/{$readyVideo->id}/720p/segment_00001.ts",
        ])->get("/api/v1/internal/playback/auth?video_id={$readyVideo->id}&token=invalid-token&expires={$expiresAt}");

        $response2->assertStatus(403);
        $this->assertSame(0, (int) Redis::get("tenant:{$tenant->id}:egress:{$date}"));
    }
}
