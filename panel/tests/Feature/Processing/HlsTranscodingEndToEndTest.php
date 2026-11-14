<?php

declare(strict_types=1);

namespace Tests\Feature\Processing;

use App\Domain\Processing\Enums\OutboxStatus;
use App\Domain\Processing\Models\MediaOutboxMessage;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Uploads\Models\UploadSession;
use App\Domain\Videos\Models\Video;
use App\Enums\UploadSessionStatus;
use App\Enums\VideoStatus;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

final class HlsTranscodingEndToEndTest extends TestCase
{
    protected ?User $testUser = null;
    // DO NOT use RefreshDatabase or DatabaseTransactions!
    // External processes (wiz_worker calling http://panel:8000/api/v1/internal/processing/callback)
    // connect to PostgreSQL independently. An uncommitted test transaction hides records from the web server.

    private ?Tenant $testTenant = null;

    private ?Video $testVideo = null;

    private ?string $sourceKey = null;

    protected function tearDown(): void
    {
        // Clean up test files in MinIO and DB records to keep environment pristine
        try {
            if ($this->sourceKey !== null) {
                Storage::disk('minio')->delete($this->sourceKey);
            }

            if ($this->testTenant !== null && $this->testVideo !== null) {
                $processedPrefix = "tenants/{$this->testTenant->id}/videos/{$this->testVideo->id}";
                Storage::disk('minio-processed')->deleteDirectory($processedPrefix);
            }

            if ($this->testVideo !== null) {
                if (! empty($this->testVideo->thumbnail_path)) {
                    Storage::disk('thumbnails')->delete($this->testVideo->thumbnail_path);
                }

                $this->testVideo->assets()->delete();
                $this->testVideo->uploadSessions()->delete();
                MediaOutboxMessage::where('aggregate_id', $this->testVideo->id)->delete();
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

    public function test_end_to_end_hls_transcoding_and_asset_registration(): void
    {
        $fixturePath = base_path('tests/Fixtures/fixture_720p.mp4');
        $this->assertFileExists($fixturePath, 'Test fixture fixture_720p.mp4 must exist');

        // Create Tenant, User, Video, UploadSession directly without transactions
        $this->testTenant = Tenant::factory()->create();
        $this->testUser = User::factory()->create();
        $this->testVideo = Video::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->testTenant->id,
            'user_id' => $this->testUser->id,
            'title' => 'E2E Fixture Transcoding Video',
            'description' => 'Test 720p fixture video for live worker transcoding verification',
            'status' => VideoStatus::UPLOADING,
        ]);

        $this->sourceKey = "tenants/{$this->testTenant->id}/videos/{$this->testVideo->id}/source.mp4";

        // 1. Upload fixture video to MinIO original bucket
        $uploadResult = Storage::disk('minio')->put($this->sourceKey, file_get_contents($fixturePath));
        $this->assertTrue($uploadResult, 'Failed to upload fixture video to MinIO');
        $this->assertTrue(Storage::disk('minio')->exists($this->sourceKey), 'Source video not found in MinIO bucket');

        $session = UploadSession::create([
            'tenant_id' => $this->testTenant->id,
            'video_id' => $this->testVideo->id,
            'upload_id' => (string) Str::uuid(),
            'disk' => 'minio',
            'object_key' => $this->sourceKey,
            'part_size' => 5242880,
            'total_parts' => 1,
            'status' => UploadSessionStatus::COMPLETED,
            'expires_at' => now()->addHours(24),
        ]);

        $jobId = (string) Str::uuid();

        // 2. Create MediaOutboxMessage and mark video QUEUED
        $outbox = MediaOutboxMessage::create([
            'event_type' => 'video.uploaded',
            'aggregate_type' => 'video',
            'aggregate_id' => $this->testVideo->id,
            'payload' => [
                'version' => '1.0',
                'job_id' => $jobId,
                'tenant_id' => $this->testTenant->id,
                'video_id' => $this->testVideo->id,
                'source_bucket' => 'videos-original',
                'source_key' => $this->sourceKey,
                'created_at' => now()->toIso8601String(),
            ],
            'status' => OutboxStatus::PENDING,
        ]);

        $this->testVideo->update(['status' => VideoStatus::QUEUED]);

        // 3. Publish outbox message to RabbitMQ media.jobs
        $this->artisan('media:publish-outbox')->assertSuccessful();
        $this->assertSame(OutboxStatus::PUBLISHED, $outbox->fresh()->status);

        // 4. Poll video status until READY or FAILED (up to 60 seconds to account for Docker execution)
        $timeout = 60.0;
        $start = microtime(true);
        $finalStatus = $this->testVideo->status;

        while ((microtime(true) - $start) < $timeout) {
            usleep(500_000); // 500ms
            $refreshed = $this->testVideo->fresh();
            if ($refreshed !== null) {
                $finalStatus = $refreshed->status;
                if ($finalStatus === VideoStatus::READY || $finalStatus === VideoStatus::FAILED) {
                    $this->testVideo = $refreshed;
                    break;
                }
            }
        }

        // 5. Assert video transitioned to READY
        $this->assertSame(
            VideoStatus::READY,
            $finalStatus,
            "Video processing failed or timed out after {$timeout}s. Status: {$finalStatus->value}. Metadata: ".json_encode($this->testVideo->fresh()?->metadata)
        );

        $this->testVideo = $this->testVideo->fresh();

        // 6. Assert duration
        $this->assertGreaterThanOrEqual(2, $this->testVideo->duration_seconds);
        $this->assertLessThanOrEqual(4, $this->testVideo->duration_seconds);

        // 7. Assert thumbnail
        $this->assertNotEmpty($this->testVideo->thumbnail_path, 'Video thumbnail_path should not be empty');
        $this->assertTrue(
            Storage::disk('thumbnails')->exists($this->testVideo->thumbnail_path),
            "Thumbnail does not exist in MinIO thumbnails disk: {$this->testVideo->thumbnail_path}"
        );

        // 8. Assert master playlist asset
        $masterAsset = $this->testVideo->assets()->where('type', 'master')->first();
        $this->assertNotNull($masterAsset, 'Master playlist asset was not registered in video_assets');
        $this->assertTrue(
            Storage::disk('minio-processed')->exists($masterAsset->object_key),
            "Master playlist file missing in minio-processed: {$masterAsset->object_key}"
        );

        // 9. Inspect master playlist content
        $masterContent = Storage::disk('minio-processed')->get($masterAsset->object_key);
        $this->assertStringContainsString('#EXTM3U', $masterContent);
        $this->assertStringContainsString('#EXT-X-STREAM-INF', $masterContent);
        $this->assertStringContainsString('stream_', $masterContent);

        // 10. Assert variant playlists and segments
        $variantAssets = $this->testVideo->assets()->whereIn('type', [\App\Enums\AssetType::HLS_PLAYLIST, 'hls_playlist', 'variant'])->get();
        $this->assertNotEmpty($variantAssets, 'Variant playlist assets were not registered');
        foreach ($variantAssets as $variant) {
            $this->assertTrue(
                Storage::disk('minio-processed')->exists($variant->object_key),
                "Variant playlist missing from MinIO: {$variant->object_key}"
            );
        }

        $segmentAssets = $this->testVideo->assets()->whereIn('type', [\App\Enums\AssetType::HLS_SEGMENT, 'hls_segment', 'segment'])->get();
        $this->assertNotEmpty($segmentAssets, 'HLS segment assets were not registered');
        foreach ($segmentAssets as $segment) {
            $this->assertTrue(
                Storage::disk('minio-processed')->exists($segment->object_key),
                "HLS segment missing from MinIO: {$segment->object_key}"
            );
        }
    }
}
