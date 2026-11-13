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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EndToEndMediaProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_pipeline_outbox_publish_and_callback_simulation(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $video = Video::factory()->forTenant($tenant)->create([
            'user_id' => $user->id,
            'status' => VideoStatus::UPLOADING,
        ]);

        $session = UploadSession::create([
            'tenant_id' => $tenant->id,
            'video_id' => $video->id,
            'upload_id' => 'mock-upload-id',
            'disk' => 'minio_originals',
            'object_key' => "tenants/{$tenant->id}/videos/{$video->id}/source.mp4",
            'part_size' => 5242880,
            'total_parts' => 1,
            'status' => UploadSessionStatus::PENDING,
            'expires_at' => now()->addHours(24),
        ]);

        // 1. Simulate video upload completion & outbox insertion
        $outbox = MediaOutboxMessage::create([
            'event_type' => 'video.uploaded',
            'aggregate_type' => 'video',
            'aggregate_id' => $video->id,
            'payload' => [
                'version' => '1.0',
                'job_id' => '019324c4-7bf3-7521-9989-d419b4ef08f1',
                'tenant_id' => $tenant->id,
                'video_id' => $video->id,
                'source_bucket' => 'videos-original',
                'source_key' => $session->object_key,
                'created_at' => now()->toIso8601String(),
            ],
            'status' => OutboxStatus::PENDING,
        ]);
        $video->update(['status' => VideoStatus::QUEUED]);

        $this->assertEquals(VideoStatus::QUEUED, $video->fresh()->status);
        $this->assertEquals(OutboxStatus::PENDING, $outbox->status);

        // 2. Publish outbox message
        $this->artisan('media:publish-outbox')->assertSuccessful();
        $this->assertEquals(OutboxStatus::PUBLISHED, $outbox->fresh()->status);

        // 3. Simulate Go worker callback
        $response = $this->withHeaders(['X-Internal-Secret' => config('services.internal.secret')])
            ->postJson('/api/v1/internal/processing/callback', [
                'job_id' => '019324c4-7bf3-7521-9989-d419b4ef08f1',
                'video_id' => $video->id,
                'status' => 'probed',
                'duration_seconds' => 64,
                'metadata' => [
                    'format' => 'mp4',
                    'video' => ['codec' => 'h264', 'width' => 1920, 'height' => 1080],
                ],
            ]);

        $response->assertOk();
        $updatedVideo = $video->fresh();
        $this->assertEquals(64, $updatedVideo->duration_seconds);
        $this->assertEquals('h264', $updatedVideo->metadata['video']['codec']);
    }
}
