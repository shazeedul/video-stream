<?php

declare(strict_types=1);

namespace Tests\Feature\Playback;

use App\Domain\Playback\Contracts\PlaybackTokenServiceInterface;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\Video;
use App\Models\User;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

final class PlaybackEndToEndTest extends TestCase
{
    // DO NOT use RefreshDatabase or DatabaseTransactions!
    // External processes (Nginx -> panel:8000) connect to PostgreSQL independently.
    // Uncommitted test transactions hide records from the web server.

    private ?Tenant $testTenant = null;

    private ?User $testUser = null;

    private ?Video $testVideo = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureMinioBucketPolicy();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->testTenant !== null && $this->testVideo !== null) {
                $processedPrefix = "tenants/{$this->testTenant->id}/videos/{$this->testVideo->id}";
                Storage::disk('minio-processed')->deleteDirectory($processedPrefix);
            }

            if ($this->testVideo !== null) {
                $this->testVideo->assets()->delete();
                $this->testVideo->uploadSessions()->delete();
                $this->testVideo->delete();
            }

            if ($this->testUser !== null) {
                $this->testUser->delete();
            }

            if ($this->testTenant !== null) {
                $this->testTenant->delete();
            }
        } catch (Throwable) {
            // Ignore teardown cleanup errors
        }

        parent::tearDown();
    }

    public function test_end_to_end_nginx_authorizes_and_streams_hls_assets_from_minio(): void
    {
        $this->testTenant = Tenant::factory()->create();
        $this->testUser = User::factory()->create();
        $this->testVideo = Video::factory()
            ->forTenant($this->testTenant)
            ->ready()
            ->public()
            ->create([
                'user_id' => $this->testUser->id,
            ]);

        $hlsPrefix = "tenants/{$this->testTenant->id}/videos/{$this->testVideo->id}/hls";

        $masterContent = <<<'M3U8'
#EXTM3U
#EXT-X-VERSION:3
#EXT-X-STREAM-INF:BANDWIDTH=800000,RESOLUTION=640x360
360p.m3u8
#EXT-X-STREAM-INF:BANDWIDTH=1400000,RESOLUTION=842x480
480p.m3u8
M3U8;

        $renditionContent = <<<'M3U8'
#EXTM3U
#EXT-X-VERSION:3
#EXT-X-TARGETDURATION:6
#EXT-X-MEDIA-SEQUENCE:0
#EXTINF:6.000000,
segment_000.ts
#EXT-X-ENDLIST
M3U8;

        $segmentContent = 'test ts segment content 1234567890';

        Storage::disk('minio-processed')->put("{$hlsPrefix}/master.m3u8", $masterContent);
        Storage::disk('minio-processed')->put("{$hlsPrefix}/360p.m3u8", $renditionContent);
        Storage::disk('minio-processed')->put("{$hlsPrefix}/segment_000.ts", $segmentContent);

        /** @var PlaybackTokenServiceInterface $tokenService */
        $tokenService = app(PlaybackTokenServiceInterface::class);
        $expires = time() + 3600;
        $token = $tokenService->generateToken((string) $this->testVideo->id, $expires);

        // 1. Master manifest request
        $masterResponse = Http::timeout(10)->get("http://nginx/playback/{$this->testVideo->id}/master.m3u8?token={$token}&expires={$expires}");

        $this->assertSame(200, $masterResponse->status());
        $this->assertStringContainsString('#EXTM3U', $masterResponse->body());
        $this->assertStringContainsString('#EXT-X-STREAM-INF', $masterResponse->body());
        $this->assertSame('*', $masterResponse->header('Access-Control-Allow-Origin'));

        // 2. Rendition playlist request
        $renditionResponse = Http::timeout(10)->get("http://nginx/playback/{$this->testVideo->id}/360p.m3u8?token={$token}&expires={$expires}");

        $this->assertSame(200, $renditionResponse->status());
        $this->assertStringContainsString('segment_000.ts', $renditionResponse->body());

        // 3. Video segment request
        $segmentResponse = Http::timeout(10)->get("http://nginx/playback/{$this->testVideo->id}/segment_000.ts?token={$token}&expires={$expires}");

        $this->assertSame(200, $segmentResponse->status());
        $this->assertSame('test ts segment content 1234567890', $segmentResponse->body());
    }

    public function test_nginx_playback_rejects_unauthorized_requests(): void
    {
        $this->testTenant = Tenant::factory()->create();
        $this->testUser = User::factory()->create();
        $this->testVideo = Video::factory()
            ->forTenant($this->testTenant)
            ->ready()
            ->create([
                'user_id' => $this->testUser->id,
            ]);

        /** @var PlaybackTokenServiceInterface $tokenService */
        $tokenService = app(PlaybackTokenServiceInterface::class);

        // Expired token
        $expiredTime = time() - 3600;
        $expiredToken = $tokenService->generateToken((string) $this->testVideo->id, $expiredTime);
        $expiredResponse = Http::timeout(10)->get("http://nginx/playback/{$this->testVideo->id}/master.m3u8?token={$expiredToken}&expires={$expiredTime}");

        $this->assertSame(403, $expiredResponse->status());

        // Invalid token signature
        $expires = time() + 3600;
        $invalidResponse = Http::timeout(10)->get("http://nginx/playback/{$this->testVideo->id}/master.m3u8?token=invalid_sig&expires={$expires}");

        $this->assertSame(403, $invalidResponse->status());

        // Conflicting video ID
        $validToken = $tokenService->generateToken((string) $this->testVideo->id, $expires);
        $conflictingId = (string) Str::uuid();
        $conflictingResponse = Http::timeout(10)->get("http://nginx/playback/{$this->testVideo->id}/master.m3u8?video_id={$conflictingId}&token={$validToken}&expires={$expires}");

        $this->assertContains($conflictingResponse->status(), [400, 403]);
    }

    private function ensureMinioBucketPolicy(): void
    {
        try {
            $disk = Storage::disk('minio-processed');
            if (method_exists($disk, 'getClient')) {
                /** @var S3Client $client */
                $client = $disk->getClient();
                $bucket = (string) config('filesystems.disks.minio-processed.bucket', 'videos-processed');
                $policy = json_encode([
                    'Version' => '2012-10-17',
                    'Statement' => [
                        [
                            'Effect' => 'Allow',
                            'Principal' => ['AWS' => ['*']],
                            'Action' => ['s3:GetBucketLocation', 's3:ListBucket'],
                            'Resource' => ["arn:aws:s3:::{$bucket}"],
                        ],
                        [
                            'Effect' => 'Allow',
                            'Principal' => ['AWS' => ['*']],
                            'Action' => ['s3:GetObject'],
                            'Resource' => ["arn:aws:s3:::{$bucket}/*"],
                        ],
                    ],
                ]);

                if ($policy !== false) {
                    $client->putBucketPolicy([
                        'Bucket' => $bucket,
                        'Policy' => $policy,
                    ]);
                }
            }
        } catch (Throwable) {
            // Ignore if client fails or policy cannot be applied
        }
    }
}
