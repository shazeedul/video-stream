<?php

declare(strict_types=1);

namespace Tests\Feature\Playback;

use App\Domain\Playback\Contracts\PlaybackTokenServiceInterface;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\Video;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PlaybackStreamAndThumbnailTest extends TestCase
{
    private ?Tenant $tenant = null;

    private ?User $testUser = null;

    private ?Video $video = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->testUser = User::factory()->create();
        $this->video = Video::factory()
            ->forTenant($this->tenant)
            ->ready()
            ->public()
            ->create([
                'user_id' => $this->testUser->id,
                'thumbnail_path' => "tenants/{$this->tenant->id}/videos/test-thumb/thumbnail.jpg",
            ]);
    }

    protected function tearDown(): void
    {
        if ($this->tenant !== null && $this->video !== null) {
            Storage::disk('minio-processed')->deleteDirectory("tenants/{$this->tenant->id}/videos/{$this->video->id}");
            Storage::disk('thumbnails')->delete("tenants/{$this->tenant->id}/videos/test-thumb/thumbnail.jpg");
        }

        parent::tearDown();
    }

    public function test_playback_stream_controller_serves_m3u8_with_valid_token(): void
    {
        $hlsPath = "tenants/{$this->tenant->id}/videos/{$this->video->id}/hls/master.m3u8";
        Storage::disk('minio-processed')->put($hlsPath, "#EXTM3U\n#EXT-X-VERSION:6");

        /** @var PlaybackTokenServiceInterface $tokenService */
        $tokenService = app(PlaybackTokenServiceInterface::class);
        $expires = time() + 3600;
        $token = $tokenService->generateToken((string) $this->video->id, $expires);

        $response = $this->get("/playback/{$this->video->id}/master.m3u8?token={$token}&expires={$expires}");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.apple.mpegurl');
        $response->assertHeader('Access-Control-Allow-Origin', '*');
        $this->assertStringContainsString('#EXTM3U', $response->streamedContent());
    }

    public function test_playback_stream_controller_rejects_unauthorized_token(): void
    {
        $response = $this->get("/playback/{$this->video->id}/master.m3u8?token=invalid&expires=12345");

        $response->assertStatus(403);
    }

    public function test_thumbnail_controller_serves_thumbnail_image(): void
    {
        $thumbPath = "tenants/{$this->tenant->id}/videos/test-thumb/thumbnail.jpg";
        Storage::disk('thumbnails')->put($thumbPath, 'fake-jpeg-bytes');

        $response = $this->get("/thumbnails/{$this->video->id}");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
        $response->assertHeader('Access-Control-Allow-Origin', '*');
        $this->assertSame('fake-jpeg-bytes', $response->streamedContent());
    }

    public function test_thumbnail_controller_serves_via_storage_route(): void
    {
        $thumbPath = "tenants/{$this->tenant->id}/videos/test-thumb/thumbnail.jpg";
        Storage::disk('thumbnails')->put($thumbPath, 'fake-jpeg-bytes');

        $response = $this->get("/storage/tenants/{$this->tenant->id}/videos/test-thumb/thumbnail.jpg");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame('fake-jpeg-bytes', $response->streamedContent());
    }
}
