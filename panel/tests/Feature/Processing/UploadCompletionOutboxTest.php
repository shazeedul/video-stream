<?php

declare(strict_types=1);

namespace Tests\Feature\Processing;

use App\Domain\Processing\Enums\OutboxStatus;
use App\Domain\Processing\Models\MediaOutboxMessage;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Uploads\Models\UploadSession;
use App\Domain\Uploads\Services\MinioMultipartUploadService;
use App\Domain\Videos\Models\Video;
use App\Enums\UploadSessionStatus;
use App\Enums\VideoStatus;
use App\Models\User;
use Aws\Result;
use Aws\S3\S3Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class UploadCompletionOutboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_upload_atomically_transitions_video_to_queued_and_records_outbox_message(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $video = Video::factory()->for($tenant)->create([
            'user_id' => $user->id,
            'status' => VideoStatus::UPLOADING,
        ]);

        $session = UploadSession::create([
            'tenant_id' => $tenant->id,
            'video_id' => $video->id,
            'upload_id' => 'upload-12345',
            'disk' => 'minio',
            'object_key' => "tenants/{$tenant->id}/videos/{$video->id}/source.mp4",
            'part_size' => 5242880,
            'total_parts' => 1,
            'status' => UploadSessionStatus::PENDING,
            'expires_at' => now()->addHours(24),
        ]);

        $mockS3 = Mockery::mock(S3Client::class);
        $mockS3->shouldReceive('completeMultipartUpload')
            ->once()
            ->andReturn(new Result([
                'Location' => "http://minio:9000/videos-original/{$session->object_key}",
                'Bucket' => 'videos-original',
                'Key' => $session->object_key,
                'ETag' => '"mock-etag"',
            ]));

        $mockS3->shouldReceive('headObject')
            ->once()
            ->andReturn(new Result([
                'ContentLength' => 10485760,
                'ContentType' => 'video/mp4',
            ]));

        $service = app(MinioMultipartUploadService::class);
        $completedVideo = $service->completeUpload(
            $session,
            [['PartNumber' => 1, 'ETag' => 'mock-etag']],
            $mockS3
        );

        $this->assertEquals(VideoStatus::QUEUED, $completedVideo->status);

        $outbox = MediaOutboxMessage::where('aggregate_id', $video->id)->first();
        $this->assertNotNull($outbox);
        $this->assertEquals('video.uploaded', $outbox->event_type);
        $this->assertEquals(OutboxStatus::PENDING, $outbox->status);
        $this->assertEquals($video->id, $outbox->payload['video_id']);
        $this->assertEquals($tenant->id, $outbox->payload['tenant_id']);
        $this->assertEquals('videos-original', $outbox->payload['source_bucket']);
        $this->assertEquals($session->object_key, $outbox->payload['source_key']);
    }
}
