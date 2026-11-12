<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Uploads\Contracts\MultipartUploadServiceInterface;
use App\Domain\Uploads\Models\UploadSession;
use App\Domain\Videos\Models\Video;
use App\Enums\TenantRole;
use App\Enums\UploadSessionStatus;
use App\Enums\VideoStatus;
use App\Models\User;

test('member can initiate multipart upload for their video', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => VideoStatus::DRAFT]);

    $mockService = Mockery::mock(MultipartUploadServiceInterface::class);
    $mockService->shouldReceive('initiateUpload')->once()->andReturn(
        UploadSession::factory()->make(['video_id' => $video->id, 'upload_id' => 'up-123', 'total_parts' => 4])
    );
    app()->instance(MultipartUploadServiceInterface::class, $mockService);

    $this->actingAs($user)
        ->postJson(route('portal.videos.upload.initiate', $video), [
            'filename' => 'video.mp4',
            'file_size' => 20 * 1024 * 1024,
            'content_type' => 'video/mp4',
        ])
        ->assertOk()
        ->assertJsonStructure(['upload_id', 'part_size', 'total_parts']);
});

test('user from another tenant cannot access upload endpoints', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userB = User::factory()->create();
    $tenantB->users()->attach($userB->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $videoA = Video::factory()->create(['tenant_id' => $tenantA->id]);

    $this->actingAs($userB)
        ->postJson(route('portal.videos.upload.initiate', $videoA), [
            'filename' => 'hack.mp4',
            'file_size' => 10 * 1024 * 1024,
            'content_type' => 'video/mp4',
        ])
        ->assertNotFound();
});

test('viewer role cannot initiate or modify upload (forbidden 403)', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->create();
    $tenant->users()->attach($viewer->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::VIEWER->value]);

    $video = Video::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($viewer)
        ->postJson(route('portal.videos.upload.initiate', $video), [
            'filename' => 'video.mp4',
            'file_size' => 20 * 1024 * 1024,
            'content_type' => 'video/mp4',
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->postJson(route('portal.videos.upload.parts', $video), [
            'part_numbers' => [1],
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->postJson(route('portal.videos.upload.complete', $video), [
            'parts' => [['PartNumber' => 1, 'ETag' => 'etag']],
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->postJson(route('portal.videos.upload.abort', $video))
        ->assertForbidden();
});

test('member cannot initiate upload on another members video', function (): void {
    $tenant = Tenant::factory()->create();
    $member1 = User::factory()->create();
    $member2 = User::factory()->create();
    $tenant->users()->attach($member1->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);
    $tenant->users()->attach($member2->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $member1->id]);

    $this->actingAs($member2)
        ->postJson(route('portal.videos.upload.initiate', $video), [
            'filename' => 'video.mp4',
            'file_size' => 20 * 1024 * 1024,
            'content_type' => 'video/mp4',
        ])
        ->assertForbidden();
});

test('tenant admin can initiate multipart upload for any members video', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $tenant->users()->attach($admin->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::ADMIN->value]);
    $tenant->users()->attach($member->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $member->id]);

    $mockService = Mockery::mock(MultipartUploadServiceInterface::class);
    $mockService->shouldReceive('initiateUpload')->once()->andReturn(
        UploadSession::factory()->make(['video_id' => $video->id, 'upload_id' => 'up-admin', 'total_parts' => 2])
    );
    app()->instance(MultipartUploadServiceInterface::class, $mockService);

    $this->actingAs($admin)
        ->postJson(route('portal.videos.upload.initiate', $video), [
            'filename' => 'admin-video.mp4',
            'file_size' => 10 * 1024 * 1024,
            'content_type' => 'video/mp4',
        ])
        ->assertOk()
        ->assertJsonStructure(['upload_id', 'part_size', 'total_parts']);
});

test('initiate upload validates filename, file_size, and content_type', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson(route('portal.videos.upload.initiate', $video), [
            'filename' => '',
            'file_size' => 0,
            'content_type' => 'application/pdf',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['filename', 'file_size', 'content_type']);
});

test('can request presigned part urls for active upload session', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
    $session = UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'status' => UploadSessionStatus::PENDING,
        'total_parts' => 5,
        'expires_at' => now()->addHours(24),
    ]);

    $expectedUrls = [
        1 => 'https://minio.test/upload/part1',
        2 => 'https://minio.test/upload/part2',
    ];

    $mockService = Mockery::mock(MultipartUploadServiceInterface::class);
    $mockService->shouldReceive('getPresignedPartUrls')
        ->once()
        ->with(Mockery::on(fn ($s) => $s->id === $session->id), [1, 2])
        ->andReturn($expectedUrls);
    app()->instance(MultipartUploadServiceInterface::class, $mockService);

    $this->actingAs($user)
        ->postJson(route('portal.videos.upload.parts', $video), [
            'part_numbers' => [1, 2],
        ])
        ->assertOk()
        ->assertJson([
            'parts' => [
                '1' => 'https://minio.test/upload/part1',
                '2' => 'https://minio.test/upload/part2',
            ],
        ]);
});

test('requesting part urls fails with 422 if session is expired or not pending', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
    UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'status' => UploadSessionStatus::EXPIRED,
        'expires_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->postJson(route('portal.videos.upload.parts', $video), [
            'part_numbers' => [1],
        ])
        ->assertUnprocessable();
});

test('can complete multipart upload with valid parts', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => VideoStatus::UPLOADING]);
    $session = UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'status' => UploadSessionStatus::PENDING,
        'total_parts' => 2,
        'expires_at' => now()->addHours(24),
    ]);

    $mockService = Mockery::mock(MultipartUploadServiceInterface::class);
    $mockService->shouldReceive('completeUpload')
        ->once()
        ->with(
            Mockery::on(fn ($s) => $s->id === $session->id),
            [
                ['PartNumber' => 1, 'ETag' => '"etag1"'],
                ['PartNumber' => 2, 'ETag' => '"etag2"'],
            ]
        )
        ->andReturn($video);
    app()->instance(MultipartUploadServiceInterface::class, $mockService);

    $this->actingAs($user)
        ->postJson(route('portal.videos.upload.complete', $video), [
            'parts' => [
                ['PartNumber' => 1, 'ETag' => '"etag1"'],
                ['part_number' => 2, 'etag' => '"etag2"'],
            ],
        ])
        ->assertOk();
});

test('complete upload fails with 422 if session is not active or expired', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
    UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'status' => UploadSessionStatus::COMPLETED,
    ]);

    $this->actingAs($user)
        ->postJson(route('portal.videos.upload.complete', $video), [
            'parts' => [
                ['PartNumber' => 1, 'ETag' => '"etag1"'],
            ],
        ])
        ->assertUnprocessable();
});

test('can abort multipart upload', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => VideoStatus::UPLOADING]);
    $session = UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'status' => UploadSessionStatus::PENDING,
        'expires_at' => now()->addHours(24),
    ]);

    $mockService = Mockery::mock(MultipartUploadServiceInterface::class);
    $mockService->shouldReceive('abortUpload')
        ->once()
        ->with(Mockery::on(fn ($s) => $s->id === $session->id));
    app()->instance(MultipartUploadServiceInterface::class, $mockService);

    $this->actingAs($user)
        ->postJson(route('portal.videos.upload.abort', $video))
        ->assertOk()
        ->assertJsonStructure(['message']);
});

test('abort upload fails with 422 if session is not pending or expired', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
    UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'status' => UploadSessionStatus::ABORTED,
    ]);

    $this->actingAs($user)
        ->postJson(route('portal.videos.upload.abort', $video))
        ->assertUnprocessable();
});

test('complete upload validates parts list structure and required fields', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
    UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'status' => UploadSessionStatus::PENDING,
        'total_parts' => 2,
    ]);

    // Empty parts
    $this->actingAs($user)
        ->postJson(route('portal.videos.upload.complete', $video), [
            'parts' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parts']);

    // Missing etag in part
    $this->actingAs($user)
        ->postJson(route('portal.videos.upload.complete', $video), [
            'parts' => [
                ['PartNumber' => 1],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parts.0.etag']);

    // Missing part number in part
    $this->actingAs($user)
        ->postJson(route('portal.videos.upload.complete', $video), [
            'parts' => [
                ['ETag' => 'etag1'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parts.0.part_number']);
});

test('part urls validation rejects non-array, negative or out-of-bounds part numbers', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
    UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'status' => UploadSessionStatus::PENDING,
        'total_parts' => 3,
        'expires_at' => now()->addHours(24),
    ]);

    // Out of bounds part number (4 > 3)
    $this->actingAs($user)
        ->postJson(route('portal.videos.upload.parts', $video), [
            'part_numbers' => [4],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['part_numbers.0']);

    // Negative part number
    $this->actingAs($user)
        ->postJson(route('portal.videos.upload.parts', $video), [
            'part_numbers' => [-1],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['part_numbers.0']);
});

test('service exceptions return 422 with domain error message', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
    $session = UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'status' => UploadSessionStatus::PENDING,
        'total_parts' => 2,
        'expires_at' => now()->addHours(24),
    ]);

    $mockService = Mockery::mock(MultipartUploadServiceInterface::class);
    $mockService->shouldReceive('completeUpload')
        ->once()
        ->andThrow(new DomainException('Parts count mismatch'));
    app()->instance(MultipartUploadServiceInterface::class, $mockService);

    $this->actingAs($user)
        ->postJson(route('portal.videos.upload.complete', $video), [
            'parts' => [
                ['PartNumber' => 1, 'ETag' => 'etag1'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJson(['message' => 'Parts count mismatch']);
});
