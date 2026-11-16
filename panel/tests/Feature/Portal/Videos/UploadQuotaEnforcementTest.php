<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Uploads\Contracts\MultipartUploadServiceInterface;
use App\Domain\Uploads\Models\UploadSession;
use App\Domain\Videos\Models\Video;
use App\Enums\TenantRole;
use App\Enums\UploadSessionStatus;
use App\Enums\VideoStatus;
use App\Events\Videos\VideoStatusUpdated;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('initiating upload within quota returns 200 ok with upload id', function (): void {
    $tenant = Tenant::factory()->create([
        'settings' => ['storage_quota_bytes' => 100 * 1024 * 1024], // 100 MB
    ]);
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, [
        'id' => (string) str()->uuid(),
        'role' => TenantRole::MEMBER->value,
    ]);

    $video = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => VideoStatus::DRAFT,
    ]);

    $mockService = Mockery::mock(MultipartUploadServiceInterface::class);
    $mockService->shouldReceive('initiateUpload')
        ->once()
        ->with(
            Mockery::on(fn ($v) => $v->id === $video->id),
            'tutorial.mp4',
            20 * 1024 * 1024,
            'video/mp4'
        )
        ->andReturn(
            UploadSession::factory()->make([
                'video_id' => $video->id,
                'upload_id' => 'upload-test-123',
                'part_size' => 5 * 1024 * 1024,
                'total_parts' => 4,
                'object_key' => "tenants/{$tenant->id}/videos/{$video->id}/raw.mp4",
                'expires_at' => now()->addHours(24),
            ])
        );
    app()->instance(MultipartUploadServiceInterface::class, $mockService);

    $this->actingAs($user)
        ->postJson(route('portal.videos.upload.initiate', $video), [
            'filename' => 'tutorial.mp4',
            'file_size' => 20 * 1024 * 1024,
            'content_type' => 'video/mp4',
        ])
        ->assertOk()
        ->assertJson([
            'upload_id' => 'upload-test-123',
            'part_size' => 5 * 1024 * 1024,
            'total_parts' => 4,
        ]);
});

test('initiating upload exceeding quota returns 422 with tenant storage quota exceeded message', function (): void {
    $tenant = Tenant::factory()->create([
        'settings' => ['storage_quota_bytes' => 100 * 1024 * 1024], // 100 MB
    ]);
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, [
        'id' => (string) str()->uuid(),
        'role' => TenantRole::MEMBER->value,
    ]);

    $video = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => VideoStatus::DRAFT,
    ]);

    $this->actingAs($user)
        ->postJson(route('portal.videos.upload.initiate', $video), [
            'filename' => 'huge_video.mp4',
            'file_size' => 150 * 1024 * 1024, // 150 MB > 100 MB quota
            'content_type' => 'video/mp4',
        ])
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Tenant storage quota exceeded. Please upgrade your plan or remove existing media.',
        ]);
});

test('concurrent upload sessions in-flight upload reservation prevents concurrent uploads from exceeding quota', function (): void {
    $tenant = Tenant::factory()->create([
        'settings' => ['storage_quota_bytes' => 100 * 1024 * 1024], // 100 MB
    ]);
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, [
        'id' => (string) str()->uuid(),
        'role' => TenantRole::MEMBER->value,
    ]);

    // Video 1 has an active in-flight upload session of 60 MB (6 * 10 MB)
    $video1 = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => VideoStatus::UPLOADING,
    ]);
    UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video1->id,
        'status' => UploadSessionStatus::PENDING,
        'part_size' => 10 * 1024 * 1024,
        'total_parts' => 6, // 60 MB reserved
        'expires_at' => now()->addHours(24),
    ]);

    // Video 2 attempts to upload 50 MB -> 60 MB + 50 MB = 110 MB > 100 MB quota
    $video2 = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => VideoStatus::DRAFT,
    ]);

    $this->actingAs($user)
        ->postJson(route('portal.videos.upload.initiate', $video2), [
            'filename' => 'second_video.mp4',
            'file_size' => 50 * 1024 * 1024,
            'content_type' => 'video/mp4',
        ])
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Tenant storage quota exceeded. Please upgrade your plan or remove existing media.',
        ]);
});

test('aborting an upload session releases reserved storage and allows new upload', function (): void {
    $tenant = Tenant::factory()->create([
        'settings' => ['storage_quota_bytes' => 100 * 1024 * 1024], // 100 MB
    ]);
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, [
        'id' => (string) str()->uuid(),
        'role' => TenantRole::MEMBER->value,
    ]);

    // Video 1 has an active in-flight upload session of 60 MB
    $video1 = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => VideoStatus::UPLOADING,
    ]);
    $session1 = UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video1->id,
        'status' => UploadSessionStatus::PENDING,
        'part_size' => 10 * 1024 * 1024,
        'total_parts' => 6, // 60 MB reserved
        'expires_at' => now()->addHours(24),
    ]);

    $video2 = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => VideoStatus::DRAFT,
    ]);

    $mockService = Mockery::mock(MultipartUploadServiceInterface::class);
    $mockService->shouldReceive('abortUpload')
        ->once()
        ->with(Mockery::on(fn ($s) => $s->id === $session1->id))
        ->andReturnUsing(function (UploadSession $session): void {
            $session->update(['status' => UploadSessionStatus::ABORTED]);
        });
    $mockService->shouldReceive('initiateUpload')
        ->once()
        ->with(
            Mockery::on(fn ($v) => $v->id === $video2->id),
            'second_video.mp4',
            50 * 1024 * 1024,
            'video/mp4'
        )
        ->andReturn(
            UploadSession::factory()->make([
                'video_id' => $video2->id,
                'upload_id' => 'upload-second-456',
                'part_size' => 10 * 1024 * 1024,
                'total_parts' => 5,
            ])
        );
    app()->instance(MultipartUploadServiceInterface::class, $mockService);

    // 1. Initial attempt for Video 2 fails due to quota (60 MB + 50 MB > 100 MB)
    $this->actingAs($user)
        ->postJson(route('portal.videos.upload.initiate', $video2), [
            'filename' => 'second_video.mp4',
            'file_size' => 50 * 1024 * 1024,
            'content_type' => 'video/mp4',
        ])
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Tenant storage quota exceeded. Please upgrade your plan or remove existing media.',
        ]);

    // 2. Abort upload session for Video 1
    $this->actingAs($user)
        ->postJson(route('portal.videos.upload.abort', $video1))
        ->assertOk();

    expect($session1->fresh()->status)->toBe(UploadSessionStatus::ABORTED);

    // 3. Retry upload for Video 2 now that 60 MB is released -> should succeed
    $this->actingAs($user)
        ->postJson(route('portal.videos.upload.initiate', $video2), [
            'filename' => 'second_video.mp4',
            'file_size' => 50 * 1024 * 1024,
            'content_type' => 'video/mp4',
        ])
        ->assertOk()
        ->assertJson([
            'upload_id' => 'upload-second-456',
        ]);
});

test('soft quota on transcoding callback completes successfully even if resulting assets exceed quota', function (): void {
    Event::fake([VideoStatusUpdated::class]);

    $secret = 'test-internal-secret-token';
    config(['services.internal.secret' => $secret]);

    $tenant = Tenant::factory()->create([
        'settings' => ['storage_quota_bytes' => 50 * 1024 * 1024], // 50 MB quota
    ]);
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, [
        'id' => (string) str()->uuid(),
        'role' => TenantRole::MEMBER->value,
    ]);

    $video = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => VideoStatus::TRANSCODING,
    ]);

    // Transcoding callback returns 80 MB of assets (> 50 MB quota)
    $assets = [
        [
            'type' => 'master',
            'disk' => 'minio-processed',
            'object_key' => "tenants/{$tenant->id}/videos/{$video->id}/hls/master.m3u8",
            'size_bytes' => 30 * 1024 * 1024, // 30 MB
            'mime_type' => 'application/x-mpegURL',
            'metadata' => ['renditions' => ['1080p']],
        ],
        [
            'type' => 'hls_playlist',
            'disk' => 'minio-processed',
            'object_key' => "tenants/{$tenant->id}/videos/{$video->id}/hls/stream_0.m3u8",
            'size_bytes' => 50 * 1024 * 1024, // 50 MB
            'mime_type' => 'application/x-mpegURL',
            'metadata' => ['resolution' => '1080p'],
        ],
    ];

    $response = $this->withHeaders(['X-Internal-Secret' => $secret])
        ->postJson('/api/v1/internal/processing/callback', [
            'job_id' => (string) str()->uuid(),
            'video_id' => $video->id,
            'status' => 'ready',
            'duration_seconds' => 95,
            'thumbnail_key' => "tenants/{$tenant->id}/videos/{$video->id}/thumbnail.jpg",
            'assets' => $assets,
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'video_id' => $video->id,
        ]);

    $fresh = $video->fresh();
    expect($fresh->status)->toBe(VideoStatus::READY)
        ->and($fresh->duration_seconds)->toBe(95)
        ->and($fresh->assets)->toHaveCount(2);
});
