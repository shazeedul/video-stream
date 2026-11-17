<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Uploads\Models\UploadSession;
use App\Domain\Usage\Contracts\TenantUsageServiceInterface;
use App\Domain\Usage\Models\TenantUsageRecord;
use App\Domain\Usage\Services\TenantUsageService;
use App\Domain\Videos\Models\Video;
use App\Domain\Videos\Models\VideoAsset;
use App\Enums\AssetType;
use App\Enums\UploadSessionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('service can be resolved from container via interface', function (): void {
    $service = app(TenantUsageServiceInterface::class);

    expect($service)->toBeInstanceOf(TenantUsageService::class);
});

test('calculation sums VideoAsset records for the tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $video1 = Video::factory()->forTenant($tenant)->create();
    $video2 = Video::factory()->forTenant($tenant)->create();

    VideoAsset::factory()->create([
        'video_id' => $video1->id,
        'type' => AssetType::ORIGINAL,
        'size_bytes' => 10_000_000,
    ]);
    VideoAsset::factory()->create([
        'video_id' => $video1->id,
        'type' => AssetType::HLS_PLAYLIST,
        'size_bytes' => 5_000_000,
    ]);
    VideoAsset::factory()->create([
        'video_id' => $video2->id,
        'type' => AssetType::ORIGINAL,
        'size_bytes' => 20_000_000,
    ]);

    /** @var TenantUsageServiceInterface $service */
    $service = app(TenantUsageServiceInterface::class);
    $totalStorage = $service->getCurrentStorageBytes($tenant);

    expect($totalStorage)->toBe(35_000_000);
});

test('calculation includes active UploadSession records with status uploading or pending', function (): void {
    $tenant = Tenant::factory()->create();
    $video = Video::factory()->forTenant($tenant)->create();

    // Video asset: 10 MB
    VideoAsset::factory()->create([
        'video_id' => $video->id,
        'type' => AssetType::ORIGINAL,
        'size_bytes' => 10_000_000,
    ]);

    // Active upload session with status uploading: 3 parts * 5MB = 15 MB
    UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'status' => 'uploading',
        'part_size' => 5_000_000,
        'total_parts' => 3,
    ]);

    // Active upload session with status pending: 2 parts * 4MB = 8 MB
    UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'status' => UploadSessionStatus::PENDING,
        'part_size' => 4_000_000,
        'total_parts' => 2,
    ]);

    // Completed upload session: should NOT be added as in-flight session
    UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'status' => UploadSessionStatus::COMPLETED,
        'part_size' => 5_000_000,
        'total_parts' => 4,
    ]);

    // Aborted upload session: should NOT be added
    UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'status' => UploadSessionStatus::ABORTED,
        'part_size' => 5_000_000,
        'total_parts' => 4,
    ]);

    /** @var TenantUsageServiceInterface $service */
    $service = app(TenantUsageServiceInterface::class);
    $totalStorage = $service->getCurrentStorageBytes($tenant);

    // 10MB (asset) + 15MB (uploading) + 8MB (pending) = 33MB
    expect($totalStorage)->toBe(33_000_000);
});

test('calculation ignores soft-deleted or other tenants assets', function (): void {
    $tenant = Tenant::factory()->create();
    $video = Video::factory()->forTenant($tenant)->create();
    VideoAsset::factory()->create([
        'video_id' => $video->id,
        'size_bytes' => 12_000_000,
    ]);

    // Other active tenant
    $otherTenant = Tenant::factory()->create();
    $otherVideo = Video::factory()->forTenant($otherTenant)->create();
    VideoAsset::factory()->create([
        'video_id' => $otherVideo->id,
        'size_bytes' => 50_000_000,
    ]);
    UploadSession::factory()->create([
        'tenant_id' => $otherTenant->id,
        'video_id' => $otherVideo->id,
        'status' => 'uploading',
        'part_size' => 10_000_000,
        'total_parts' => 5,
    ]);

    // Soft-deleted tenant
    $softDeletedTenant = Tenant::factory()->create();
    $softDeletedVideo = Video::factory()->forTenant($softDeletedTenant)->create();
    VideoAsset::factory()->create([
        'video_id' => $softDeletedVideo->id,
        'size_bytes' => 30_000_000,
    ]);
    $softDeletedTenant->delete();

    /** @var TenantUsageServiceInterface $service */
    $service = app(TenantUsageServiceInterface::class);
    $totalStorage = $service->getCurrentStorageBytes($tenant);

    expect($totalStorage)->toBe(12_000_000);
});

test('hasExceededStorageQuota returns true when storage plus additional exceeds quota', function (): void {
    $tenant = Tenant::factory()->create([
        'settings' => [
            'storage_quota_bytes' => 20_000_000,
        ],
    ]);
    $video = Video::factory()->forTenant($tenant)->create();
    VideoAsset::factory()->create([
        'video_id' => $video->id,
        'size_bytes' => 15_000_000,
    ]);

    /** @var TenantUsageServiceInterface $service */
    $service = app(TenantUsageServiceInterface::class);

    // 15MB + 4MB = 19MB <= 20MB -> false
    expect($service->hasExceededStorageQuota($tenant, 4_000_000))->toBeFalse();

    // 15MB + 5MB = 20MB <= 20MB -> false (exact quota is not exceeded)
    expect($service->hasExceededStorageQuota($tenant, 5_000_000))->toBeFalse();

    // 15MB + 6MB = 21MB > 20MB -> true
    expect($service->hasExceededStorageQuota($tenant, 6_000_000))->toBeTrue();

    // Without additional bytes: 15MB <= 20MB -> false
    expect($service->hasExceededStorageQuota($tenant))->toBeFalse();
});

test('default quota of 10 GB applies when tenants settings storage_quota_bytes is null', function (): void {
    $tenant = Tenant::factory()->create([
        'settings' => null,
    ]);

    /** @var TenantUsageServiceInterface $service */
    $service = app(TenantUsageServiceInterface::class);

    $defaultQuota = 10 * 1024 * 1024 * 1024;
    expect($service->getStorageQuotaBytes($tenant))->toBe($defaultQuota);
    expect($tenant->getStorageQuotaBytes())->toBe($defaultQuota);
});

test('recalculateTenantStorage updates or creates TenantUsageRecord and returns current storage', function (): void {
    $tenant = Tenant::factory()->create();
    $video = Video::factory()->forTenant($tenant)->create();
    VideoAsset::factory()->create([
        'video_id' => $video->id,
        'size_bytes' => 18_000_000,
    ]);

    /** @var TenantUsageServiceInterface $service */
    $service = app(TenantUsageServiceInterface::class);

    $calculatedBytes = $service->recalculateTenantStorage($tenant);

    expect($calculatedBytes)->toBe(18_000_000);

    $record = TenantUsageRecord::query()
        ->where('tenant_id', $tenant->id)
        ->where('date', now()->toDateString())
        ->first();

    expect($record)->not->toBeNull();
    expect($record->storage_bytes)->toBe(18_000_000);
    expect($record->video_count)->toBe(1);
    expect($tenant->usageRecords)->toHaveCount(1);
});

test('calculation excludes expired in-flight upload sessions from storage', function (): void {
    $tenant = Tenant::factory()->create();
    $video = Video::factory()->forTenant($tenant)->create();

    // Active upload session not expired: 2 parts * 5MB = 10MB
    UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'status' => UploadSessionStatus::UPLOADING,
        'part_size' => 5_000_000,
        'total_parts' => 2,
        'expires_at' => now()->addHour(),
    ]);

    // Active upload session also not expired (future expiration): 1 part * 3MB = 3MB
    UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'status' => UploadSessionStatus::PENDING,
        'part_size' => 3_000_000,
        'total_parts' => 1,
        'expires_at' => now()->addMinutes(30),
    ]);

    // Expired upload session: should NOT be included
    UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'status' => UploadSessionStatus::UPLOADING,
        'part_size' => 8_000_000,
        'total_parts' => 2,
        'expires_at' => now()->subHour(),
    ]);

    /** @var TenantUsageServiceInterface $service */
    $service = app(TenantUsageServiceInterface::class);
    $totalStorage = $service->getCurrentStorageBytes($tenant);

    // Only 10MB + 3MB = 13MB, expired 16MB is excluded
    expect($totalStorage)->toBe(13_000_000);
});
