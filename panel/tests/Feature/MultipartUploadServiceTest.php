<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Uploads\Contracts\MultipartUploadServiceInterface;
use App\Domain\Uploads\Models\UploadSession;
use App\Domain\Videos\Models\Video;
use App\Domain\Videos\Models\VideoAsset;
use App\Enums\AssetType;
use App\Enums\UploadSessionStatus;
use App\Enums\VideoStatus;
use Aws\CommandInterface;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Request;

test('multipart upload service initiates session with minimum 5mb part size and records upload session', function (): void {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'status' => VideoStatus::DRAFT]);

    $mockS3 = Mockery::mock(S3Client::class);
    $mockS3->shouldReceive('createMultipartUpload')->once()->andReturn([
        'UploadId' => 'test-upload-id-12345',
    ]);

    $service = app(MultipartUploadServiceInterface::class);
    $session = $service->initiateUpload($video, 'test-sample.mp4', 15 * 1024 * 1024, 'video/mp4', $mockS3);

    expect($session)->toBeInstanceOf(UploadSession::class)
        ->and($session->upload_id)->toBe('test-upload-id-12345')
        ->and($session->part_size)->toBeGreaterThanOrEqual(5 * 1024 * 1024)
        ->and($session->total_parts)->toBe(3)
        ->and($session->status)->toBe(UploadSessionStatus::PENDING)
        ->and($session->object_key)->toStartWith("tenants/{$tenant->id}/videos/{$video->id}/original/")
        ->and($session->object_key)->toEndWith('.mp4')
        ->and($video->fresh()->status)->toBe(VideoStatus::UPLOADING);
});

test('initiates upload scales part size for files larger than 50gb to stay under 10000 parts', function (): void {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'status' => VideoStatus::DRAFT]);

    $mockS3 = Mockery::mock(S3Client::class);
    $mockS3->shouldReceive('createMultipartUpload')->once()->andReturn([
        'UploadId' => 'large-file-upload-id',
    ]);

    $service = app(MultipartUploadServiceInterface::class);
    // 100 GB file
    $fileSize = 100 * 1024 * 1024 * 1024;
    $session = $service->initiateUpload($video, 'large-video.mov', $fileSize, 'video/quicktime', $mockS3);

    expect($session->total_parts)->toBeLessThanOrEqual(10000)
        ->and($session->part_size)->toBeGreaterThan(5 * 1024 * 1024)
        ->and($session->total_parts * $session->part_size)->toBeGreaterThanOrEqual($fileSize);
});

test('initiates upload rejects invalid file size', function (): void {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id]);

    $mockS3 = Mockery::mock(S3Client::class);
    $service = app(MultipartUploadServiceInterface::class);

    expect(fn () => $service->initiateUpload($video, 'zero.mp4', 0, 'video/mp4', $mockS3))
        ->toThrow(InvalidArgumentException::class);
});

test('generates presigned part urls with 1 hour expiration for valid part numbers', function (): void {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id]);

    $session = UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'upload_id' => 'upload-xyz-123',
        'total_parts' => 3,
        'status' => UploadSessionStatus::PENDING,
        'expires_at' => now()->addHours(24),
    ]);

    $mockS3 = Mockery::mock(S3Client::class);
    $mockCmd1 = Mockery::mock(CommandInterface::class);
    $mockCmd2 = Mockery::mock(CommandInterface::class);

    $mockS3->shouldReceive('getCommand')
        ->with('UploadPart', Mockery::subset([
            'Key' => $session->object_key,
            'UploadId' => 'upload-xyz-123',
            'PartNumber' => 1,
        ]))
        ->once()
        ->andReturn($mockCmd1);

    $mockS3->shouldReceive('getCommand')
        ->with('UploadPart', Mockery::subset([
            'Key' => $session->object_key,
            'UploadId' => 'upload-xyz-123',
            'PartNumber' => 2,
        ]))
        ->once()
        ->andReturn($mockCmd2);

    $mockReq1 = new Request('PUT', 'http://127.0.0.1:9000/videos-original/part1?signature=abc');
    $mockReq2 = new Request('PUT', 'http://127.0.0.1:9000/videos-original/part2?signature=def');

    $mockS3->shouldReceive('createPresignedRequest')
        ->with($mockCmd1, '+1 hour')
        ->once()
        ->andReturn($mockReq1);

    $mockS3->shouldReceive('createPresignedRequest')
        ->with($mockCmd2, '+1 hour')
        ->once()
        ->andReturn($mockReq2);

    $service = app(MultipartUploadServiceInterface::class);
    $urls = $service->getPresignedPartUrls($session, [1, 2], $mockS3);

    expect($urls)->toBeArray()
        ->and($urls)->toHaveCount(2)
        ->and($urls[1])->toBe('http://127.0.0.1:9000/videos-original/part1?signature=abc')
        ->and($urls[2])->toBe('http://127.0.0.1:9000/videos-original/part2?signature=def');
});

test('presigned part urls throws exception when session is not active or expired', function (): void {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id]);

    $completedSession = UploadSession::factory()->completed()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
    ]);

    $expiredSession = UploadSession::factory()->expired()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
    ]);

    $service = app(MultipartUploadServiceInterface::class);

    expect(fn () => $service->getPresignedPartUrls($completedSession, [1]))
        ->toThrow(DomainException::class);

    expect(fn () => $service->getPresignedPartUrls($expiredSession, [1]))
        ->toThrow(DomainException::class);
});

test('presigned part urls throws exception when part number is out of bounds', function (): void {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id]);

    $session = UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'total_parts' => 3,
        'status' => UploadSessionStatus::PENDING,
    ]);

    $service = app(MultipartUploadServiceInterface::class);

    expect(fn () => $service->getPresignedPartUrls($session, [0]))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $service->getPresignedPartUrls($session, [4]))
        ->toThrow(InvalidArgumentException::class);
});

test('complete upload calls completeMultipartUpload and records original video asset and transitions status', function (): void {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => VideoStatus::UPLOADING,
    ]);

    $session = UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'upload_id' => 'upload-to-complete-123',
        'disk' => 'minio',
        'object_key' => "tenants/{$tenant->id}/videos/{$video->id}/original/test.mp4",
        'status' => UploadSessionStatus::PENDING,
        'total_parts' => 2,
    ]);

    $mockS3 = Mockery::mock(S3Client::class);
    $mockS3->shouldReceive('completeMultipartUpload')
        ->with(Mockery::on(function (array $args) use ($session): bool {
            return $args['Key'] === $session->object_key
                && $args['UploadId'] === 'upload-to-complete-123'
                && count($args['MultipartUpload']['Parts']) === 2
                && $args['MultipartUpload']['Parts'][0]['PartNumber'] === 1
                && $args['MultipartUpload']['Parts'][0]['ETag'] === '"etag-part-1"'
                && $args['MultipartUpload']['Parts'][1]['PartNumber'] === 2
                && $args['MultipartUpload']['Parts'][1]['ETag'] === '"etag-part-2"';
        }))
        ->once()
        ->andReturn(['Location' => 'http://127.0.0.1:9000/videos-original/...']);

    $mockS3->shouldReceive('headObject')
        ->with(Mockery::subset([
            'Key' => $session->object_key,
        ]))
        ->once()
        ->andReturn([
            'ContentLength' => 12 * 1024 * 1024,
            'ContentType' => 'video/mp4',
        ]);

    $service = app(MultipartUploadServiceInterface::class);
    $parts = [
        ['PartNumber' => 2, 'ETag' => '"etag-part-2"'],
        ['PartNumber' => 1, 'ETag' => '"etag-part-1"'],
    ];

    $updatedVideo = $service->completeUpload($session, $parts, $mockS3);

    expect($updatedVideo)->toBeInstanceOf(Video::class)
        ->and($updatedVideo->status)->toBe(VideoStatus::QUEUED)
        ->and($session->fresh()->status)->toBe(UploadSessionStatus::COMPLETED);

    $asset = VideoAsset::where('video_id', $video->id)->first();
    expect($asset)->not->toBeNull()
        ->and($asset->type)->toBe(AssetType::ORIGINAL)
        ->and($asset->disk)->toBe('minio')
        ->and($asset->object_key)->toBe($session->object_key)
        ->and($asset->size_bytes)->toBe(12 * 1024 * 1024)
        ->and($asset->mime_type)->toBe('video/mp4');
});

test('complete upload throws exception when session is not pending or parts array is invalid', function (): void {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id]);

    $completedSession = UploadSession::factory()->completed()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
    ]);

    $pendingSession = UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'status' => UploadSessionStatus::PENDING,
    ]);

    $service = app(MultipartUploadServiceInterface::class);

    expect(fn () => $service->completeUpload($completedSession, [['PartNumber' => 1, 'ETag' => 'etag1']]))
        ->toThrow(DomainException::class);

    expect(fn () => $service->completeUpload($pendingSession, []))
        ->toThrow(InvalidArgumentException::class);
});

test('abort upload calls abortMultipartUpload and updates session to aborted and video to draft', function (): void {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => VideoStatus::UPLOADING,
    ]);

    $session = UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'upload_id' => 'upload-to-abort-456',
        'status' => UploadSessionStatus::PENDING,
    ]);

    $mockS3 = Mockery::mock(S3Client::class);
    $mockS3->shouldReceive('abortMultipartUpload')
        ->with(Mockery::subset([
            'Key' => $session->object_key,
            'UploadId' => 'upload-to-abort-456',
        ]))
        ->once();

    $service = app(MultipartUploadServiceInterface::class);
    $service->abortUpload($session, $mockS3);

    expect($session->fresh()->status)->toBe(UploadSessionStatus::ABORTED)
        ->and($video->fresh()->status)->toBe(VideoStatus::DRAFT);
});

test('live integration with real minio: initiates, gets presigned urls, and aborts multipart upload', function (): void {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => VideoStatus::DRAFT,
    ]);

    $service = app(MultipartUploadServiceInterface::class);

    // 1. Initiate against real MinIO
    $session = $service->initiateUpload($video, 'integration-test.mp4', 12 * 1024 * 1024, 'video/mp4');

    expect($session->upload_id)->not->toBeEmpty()
        ->and($session->status)->toBe(UploadSessionStatus::PENDING)
        ->and($session->total_parts)->toBe(3)
        ->and($video->fresh()->status)->toBe(VideoStatus::UPLOADING);

    // 2. Generate presigned URLs
    $urls = $service->getPresignedPartUrls($session, [1, 2, 3]);

    expect($urls)->toHaveCount(3)
        ->and($urls[1])->toContain('partNumber=1')
        ->and($urls[2])->toContain('partNumber=2')
        ->and($urls[3])->toContain('partNumber=3');

    // 3. Abort against real MinIO
    $service->abortUpload($session);

    expect($session->fresh()->status)->toBe(UploadSessionStatus::ABORTED)
        ->and($video->fresh()->status)->toBe(VideoStatus::DRAFT);
});
