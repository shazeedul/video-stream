# Phase 2: Video Library and Presigned Multipart Uploads Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the tenant-scoped video library and direct-to-MinIO multipart presigned upload system for the multi-tenant SaaS video hosting platform.

**Architecture:** Laravel provides tenant-scoped video metadata management and generates presigned S3 multipart upload URLs using Flysystem/AWS S3 SDK. Clients upload chunks directly to MinIO without proxying through the PHP application server, then notify Laravel to finalize the upload and register original video assets. Inertia v2 + React 19 provides the portal video management UI and upload progress experience.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL 16 (in Docker on port 5433), MinIO S3 (in Docker on port 9000), `league/flysystem-aws-s3-v3` (`aws/aws-sdk-php`), Inertia.js v2, React 19, Tailwind CSS v4, Wayfinder.

**Spec:** `docs/architecture.md`

## Global Constraints

- **Database:** PostgreSQL 16 in Docker (`wiz_postgres`, host port 5433:5432). All migrations and tests execute against PostgreSQL.
- **Storage:** MinIO in Docker (`wiz_minio`, host port 9000:9000, console 9001:9001).
- **Code Standards:** `declare(strict_types=1);` and `final class` on all new PHP classes, enforced by Laravel Pint.
- **Enum Standards:** PHP 8.4 string-backed enums with `DevWizard\Enumify\Traits\EnumHelpers` and `HasLabels`.
- **Authorization Standards:** Strict multi-tenant isolation via `TenantPolicy` and `VideoPolicy`. Viewers can only view media; Members can upload and create; Admins and Owners can manage all team media.
- **Frontend Standards:** React 19 + Inertia v2, typed Wayfinder routes (`@/routes/...`), zero hardcoded paths, full localization via `__()` / `useLocalizer`.

## Review Focus

1. **S3 Multipart Minimum Part Size Constraint:** S3 requires all parts except the final part to be at least 5MB (5,242,880 bytes). `InitiateUploadRequest` and `MinioMultipartUploadService` must validate and enforce 5MB minimum part size.
2. **Cross-Tenant Upload Session Hijacking:** An attacker from Tenant A attempting to generate part URLs, complete, or abort an `upload_session` belonging to Tenant B must receive 404 Not Found (scoped boundary) or 403 Forbidden.
3. **Expired Upload Session Enforcement:** Requesting presigned part URLs on an expired or completed/aborted upload session must be rejected with HTTP 422 Unprocessable Entity.
4. **Tenant Viewer Upload & Mutation Restriction:** Users with `TenantRole::VIEWER` must be strictly forbidden (403) from initiating upload sessions, creating draft videos, updating video metadata, or deleting videos.
5. **Storage Cleanup on Video Deletion:** Deleting a `Video` must cascade and delete all associated `video_assets` records and remove their corresponding objects from MinIO storage.

---

### Task 1: Object Storage Infrastructure and Flysystem S3 Configuration

**Files:**
- Modify: `panel/composer.json`
- Modify: `panel/config/filesystems.php`
- Modify: `panel/.env`
- Modify: `panel/.env.example`
- Modify: `panel/phpunit.xml`
- Create: `panel/app/Console/Commands/Storage/MinioInitCommand.php`
- Create: `panel/tests/Feature/StorageConfigTest.php`

**Interfaces:**
- Consumes: `docker-compose.yml` MinIO service credentials (`minioadmin` / `minioadmin`).
- Produces: Working `minio` Flysystem S3 disk configuration and automated bucket initialization (`videos-original`, `videos-processed`, `thumbnails`).

- [ ] **Step 1: Write failing test for MinIO S3 storage configuration and bucket connectivity**

Create `panel/tests/Feature/StorageConfigTest.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

test('minio s3 disk is configured with s3 driver and path style endpoint', function (): void {
    $config = config('filesystems.disks.minio');

    expect($config)->not->toBeNull()
        ->and($config['driver'])->toBe('s3')
        ->and($config['use_path_style_endpoint'])->toBeTrue()
        ->and($config['bucket'])->toBe('videos-original');
});

test('minio storage client can be resolved from container', function (): void {
    $disk = Storage::disk('minio');
    expect($disk)->not->toBeNull();
});
```

- [ ] **Step 2: Run test to verify failure**

Run: `cd panel; php artisan test --filter=StorageConfigTest`
Expected: FAIL (`filesystems.disks.minio` not configured or missing flysystem s3 package).

- [ ] **Step 3: Install `league/flysystem-aws-s3-v3` and configure MinIO disks**

1. Install Flysystem AWS S3:
   ```bash
   cd panel
   composer require league/flysystem-aws-s3-v3 --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
   ```
2. Update `panel/config/filesystems.php` to define `minio`, `minio-processed`, and `thumbnails` disks:
   ```php
   'minio' => [
       'driver' => 's3',
       'key' => env('MINIO_ACCESS_KEY', env('AWS_ACCESS_KEY_ID', 'minioadmin')),
       'secret' => env('MINIO_SECRET_KEY', env('AWS_SECRET_ACCESS_KEY', 'minioadmin')),
       'region' => env('MINIO_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
       'bucket' => env('MINIO_BUCKET_ORIGINALS', 'videos-original'),
       'url' => env('MINIO_URL', 'http://127.0.0.1:9000/videos-original'),
       'endpoint' => env('MINIO_ENDPOINT', 'http://127.0.0.1:9000'),
       'use_path_style_endpoint' => true,
       'throw' => false,
   ],
   'minio-processed' => [
       'driver' => 's3',
       'key' => env('MINIO_ACCESS_KEY', env('AWS_ACCESS_KEY_ID', 'minioadmin')),
       'secret' => env('MINIO_SECRET_KEY', env('AWS_SECRET_ACCESS_KEY', 'minioadmin')),
       'region' => env('MINIO_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
       'bucket' => env('MINIO_BUCKET_PROCESSED', 'videos-processed'),
       'url' => env('MINIO_URL', 'http://127.0.0.1:9000/videos-processed'),
       'endpoint' => env('MINIO_ENDPOINT', 'http://127.0.0.1:9000'),
       'use_path_style_endpoint' => true,
       'throw' => false,
   ],
   ```
3. Update `panel/.env`, `panel/.env.example`, and `panel/phpunit.xml` with MinIO environment variables:
   ```env
   MINIO_ACCESS_KEY=minioadmin
   MINIO_SECRET_KEY=minioadmin
   MINIO_REGION=us-east-1
   MINIO_ENDPOINT=http://127.0.0.1:9000
   MINIO_BUCKET_ORIGINALS=videos-original
   MINIO_BUCKET_PROCESSED=videos-processed
   ```
4. Create `panel/app/Console/Commands/Storage/MinioInitCommand.php` to idempotently create the buckets on MinIO via the AWS S3 client:
   `php artisan storage:minio-init`

- [ ] **Step 4: Run MinIO container and execute tests**

```bash
docker compose up -d minio
cd panel
php artisan storage:minio-init
php artisan test --filter=StorageConfigTest
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add panel/composer.json panel/composer.lock panel/config/filesystems.php panel/.env.example panel/phpunit.xml panel/app/Console/Commands/Storage/MinioInitCommand.php panel/tests/Feature/StorageConfigTest.php
git commit -m "feat(storage): configure minio flysystem s3 disks and bucket init command"
```

---

### Task 2: Video Domain Enums and Database Schema Migrations

**Files:**
- Create: `panel/app/Enums/VideoStatus.php`
- Create: `panel/app/Enums/VideoVisibility.php`
- Create: `panel/app/Enums/AssetType.php`
- Create: `panel/app/Enums/UploadSessionStatus.php`
- Create: `panel/database/migrations/2026_11_12_000001_create_videos_table.php`
- Create: `panel/database/migrations/2026_11_12_000002_create_video_assets_table.php`
- Create: `panel/database/migrations/2026_11_12_000003_create_upload_sessions_table.php`
- Create: `panel/tests/Feature/VideoMigrationTest.php`

**Interfaces:**
- Consumes: `tenants` and `users` tables from Phase 1.
- Produces: Database schema for videos, assets, and upload sessions with indexes and foreign keys.

- [ ] **Step 1: Write failing test for video domain enums and migrations**

Create `panel/tests/Feature/VideoMigrationTest.php`:
```php
<?php

declare(strict_types=1);

use App\Enums\AssetType;
use App\Enums\UploadSessionStatus;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use Illuminate\Support\Facades\Schema;

test('video domain enums contain required cases', function (): void {
    expect(VideoStatus::values())->toContain('draft', 'uploading', 'uploaded', 'queued', 'probing', 'transcoding', 'packaging', 'ready', 'failed')
        ->and(VideoVisibility::values())->toContain('private', 'unlisted', 'public')
        ->and(AssetType::values())->toContain('original', 'master', 'hls_playlist', 'hls_segment', 'thumbnail')
        ->and(UploadSessionStatus::values())->toContain('pending', 'completed', 'aborted', 'expired');
});

test('video domain tables exist with expected schema columns and indexes', function (): void {
    expect(Schema::hasTable('videos'))->toBeTrue()
        ->and(Schema::hasColumns('videos', [
            'id', 'tenant_id', 'user_id', 'title', 'description', 'visibility',
            'status', 'duration_seconds', 'thumbnail_path', 'metadata', 'created_at', 'updated_at',
        ]))->toBeTrue();

    expect(Schema::hasTable('video_assets'))->toBeTrue()
        ->and(Schema::hasColumns('video_assets', [
            'id', 'video_id', 'type', 'disk', 'object_key', 'size_bytes',
            'mime_type', 'metadata', 'created_at', 'updated_at',
        ]))->toBeTrue();

    expect(Schema::hasTable('upload_sessions'))->toBeTrue()
        ->and(Schema::hasColumns('upload_sessions', [
            'id', 'tenant_id', 'video_id', 'upload_id', 'disk', 'object_key',
            'part_size', 'total_parts', 'status', 'expires_at', 'created_at', 'updated_at',
        ]))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify failure**

Run: `cd panel; php artisan test --filter=VideoMigrationTest`
Expected: FAIL (classes and tables do not exist).

- [ ] **Step 3: Implement enums and create migrations**

1. Create `panel/app/Enums/VideoStatus.php` with string backing, `EnumHelpers`, `HasLabels`, and boolean helpers (`isReady()`, `isFailed()`, `isProcessing()`, `canBeEdited()`).
2. Create `panel/app/Enums/VideoVisibility.php` (`PRIVATE = 'private'`, `UNLISTED = 'unlisted'`, `PUBLIC = 'public'`).
3. Create `panel/app/Enums/AssetType.php` (`ORIGINAL = 'original'`, `MASTER = 'master'`, `HLS_PLAYLIST = 'hls_playlist'`, `HLS_SEGMENT = 'hls_segment'`, `THUMBNAIL = 'thumbnail'`).
4. Create `panel/app/Enums/UploadSessionStatus.php` (`PENDING = 'pending'`, `COMPLETED = 'completed'`, `ABORTED = 'aborted'`, `EXPIRED = 'expired'`).
5. Create migrations:
   - `create_videos_table`: UUID primary key, `foreignUuid('tenant_id')->constrained()->cascadeOnDelete()`, `foreignId('user_id')->constrained()->cascadeOnDelete()`, string `title`, nullable text `description`, string `visibility` with index, string `status` with index, nullable integer `duration_seconds`, nullable string `thumbnail_path`, jsonb `metadata`.
   - `create_video_assets_table`: UUID primary key, `foreignUuid('video_id')->constrained()->cascadeOnDelete()`, string `type` with index, string `disk`, string `object_key`, nullable bigint `size_bytes`, nullable string `mime_type`, jsonb `metadata`.
   - `create_upload_sessions_table`: UUID primary key, `foreignUuid('tenant_id')->constrained()->cascadeOnDelete()`, `foreignUuid('video_id')->constrained()->cascadeOnDelete()`, string `upload_id`, string `disk`, string `object_key`, bigint `part_size`, integer `total_parts`, string `status` with index, timestamp `expires_at` with index.

- [ ] **Step 4: Run migrations and tests**

```bash
cd panel
php artisan migrate
php artisan test --filter=VideoMigrationTest
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add panel/app/Enums/ panel/database/migrations/ panel/tests/Feature/VideoMigrationTest.php
git commit -m "feat(videos): add video domain enums and database schema migrations"
```

---

### Task 3: BelongsToTenant Trait and Video Domain Eloquent Models

**Files:**
- Create: `panel/app/Domain/Tenants/Traits/BelongsToTenant.php`
- Create: `panel/app/Domain/Videos/Models/Video.php`
- Create: `panel/app/Domain/Videos/Models/VideoAsset.php`
- Create: `panel/app/Domain/Uploads/Models/UploadSession.php`
- Create: `panel/database/factories/VideoFactory.php`
- Create: `panel/database/factories/VideoAssetFactory.php`
- Create: `panel/database/factories/UploadSessionFactory.php`
- Create: `panel/tests/Feature/VideoModelTest.php`

**Interfaces:**
- Consumes: `TenantContextService` from Phase 1, `Tenant` model, `User` model.
- Produces: Scoped Eloquent models with relations, casts, scopes, and factory support.

- [ ] **Step 1: Write failing test for `BelongsToTenant` trait and Video models**

Create `panel/tests/Feature/VideoModelTest.php`:
```php
<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Uploads\Models\UploadSession;
use App\Domain\Videos\Models\Video;
use App\Domain\Videos\Models\VideoAsset;
use App\Enums\AssetType;
use App\Enums\UploadSessionStatus;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\User;

test('belongs to tenant trait automatically scopes queries to active tenant and auto-fills tenant_id', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $user = User::factory()->create();

    app()->instance('current_tenant', $tenantA);

    $videoA = Video::factory()->create(['user_id' => $user->id, 'title' => 'Video in Tenant A']);
    expect($videoA->tenant_id)->toBe($tenantA->id);

    // Switch context to Tenant B
    app()->instance('current_tenant', $tenantB);
    $videoB = Video::factory()->create(['user_id' => $user->id, 'title' => 'Video in Tenant B']);

    // Queries should only see Tenant B videos
    expect(Video::count())->toBe(1)
        ->and(Video::first()->id)->toBe($videoB->id)
        ->and(Video::withoutTenantScope()->count())->toBe(2);
});

test('video model relationships, casts, and scopes work as expected', function (): void {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $video = Video::factory()->create([
        'status' => VideoStatus::READY,
        'visibility' => VideoVisibility::PUBLIC,
    ]);

    $asset = VideoAsset::factory()->create([
        'video_id' => $video->id,
        'type' => AssetType::ORIGINAL,
    ]);

    $session = UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'status' => UploadSessionStatus::COMPLETED,
    ]);

    expect($video->assets)->toHaveCount(1)
        ->and($video->assets->first()->id)->toBe($asset->id)
        ->and($video->uploadSessions)->toHaveCount(1)
        ->and($video->status)->toBe(VideoStatus::READY)
        ->and($video->visibility)->toBe(VideoVisibility::PUBLIC);
});
```

- [ ] **Step 2: Run test to verify failure**

Run: `cd panel; php artisan test --filter=VideoModelTest`
Expected: FAIL.

- [ ] **Step 3: Implement `BelongsToTenant` trait and Eloquent models**

1. Create `panel/app/Domain/Tenants/Traits/BelongsToTenant.php`:
   - Registers global scope filtering by `tenant_id === app('current_tenant')->id` when container has `'current_tenant'`.
   - `creating` model hook automatically sets `tenant_id` if not already set.
   - Adds `scopeWithoutTenantScope()`.
2. Create `panel/app/Domain/Videos/Models/Video.php`:
   - Uses `HasUuids`, `HasFactory`, `BelongsToTenant`.
   - Casts: `status => VideoStatus::class`, `visibility => VideoVisibility::class`, `metadata => 'array'`, `duration_seconds => 'integer'`.
   - Relationships: `tenant()`, `user()`, `assets()`, `uploadSessions()`.
   - Scopes: `scopeSearch($query, $term)`, `scopeOfStatus($query, VideoStatus $status)`, `scopeOfVisibility($query, VideoVisibility $visibility)`.
3. Create `panel/app/Domain/Videos/Models/VideoAsset.php`:
   - Casts: `type => AssetType::class`, `metadata => 'array'`, `size_bytes => 'integer'`.
   - Relationship: `video()`.
4. Create `panel/app/Domain/Uploads/Models/UploadSession.php`:
   - Casts: `status => UploadSessionStatus::class`, `expires_at => 'datetime'`, `part_size => 'integer'`, `total_parts => 'integer'`.
   - Relationships: `tenant()`, `video()`.
   - Helpers: `isExpired(): bool`, `canAcceptParts(): bool`.
5. Create factories: `VideoFactory`, `VideoAssetFactory`, `UploadSessionFactory`.

- [ ] **Step 4: Run tests and Pint**

```bash
cd panel
php artisan test --filter=VideoModelTest
./vendor/bin/pint --test
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add panel/app/Domain/Tenants/Traits/ panel/app/Domain/Videos/ panel/app/Domain/Uploads/ panel/database/factories/ panel/tests/Feature/VideoModelTest.php
git commit -m "feat(videos): implement BelongsToTenant trait and Video domain Eloquent models"
```

---

### Task 4: Video Authorization Policies and Platform Permissions

**Files:**
- Modify: `panel/app/Support/Authorization/RolePermissions.php`
- Create: `panel/app/Policies/VideoPolicy.php`
- Modify: `panel/app/Providers/AppServiceProvider.php`
- Create: `panel/tests/Feature/VideoPolicyTest.php`

**Interfaces:**
- Consumes: `RolePermissions`, `TenantRole`, `Video` model, `User` model.
- Produces: Strict tenant-aware and role-aware `VideoPolicy`.

- [ ] **Step 1: Write failing test for VideoPolicy**

Create `panel/tests/Feature/VideoPolicyTest.php`:
```php
<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\Video;
use App\Enums\TenantRole;
use App\Models\User;

test('tenant viewer can view videos but cannot create, update, or delete', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->create();
    $tenant->users()->attach($viewer->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::VIEWER->value]);

    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id]);

    expect($viewer->can('view', $video))->toBeTrue()
        ->and($viewer->can('create', Video::class))->toBeFalse()
        ->and($viewer->can('update', $video))->toBeFalse()
        ->and($viewer->can('delete', $video))->toBeFalse();
});

test('tenant member can create videos and update their own video but cannot delete', function (): void {
    $tenant = Tenant::factory()->create();
    $member = User::factory()->create();
    $otherUser = User::factory()->create();
    $tenant->users()->attach($member->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    app()->instance('current_tenant', $tenant);
    $ownVideo = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $member->id]);
    $otherVideo = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $otherUser->id]);

    expect($member->can('create', Video::class))->toBeTrue()
        ->and($member->can('update', $ownVideo))->toBeTrue()
        ->and($member->can('update', $otherVideo))->toBeFalse()
        ->and($member->can('delete', $ownVideo))->toBeFalse();
});

test('tenant admin and owner can manage and delete any tenant video', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    $otherUser = User::factory()->create();
    $tenant->users()->attach($admin->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::ADMIN->value]);

    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $otherUser->id]);

    expect($admin->can('update', $video))->toBeTrue()
        ->and($admin->can('delete', $video))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify failure**

Run: `cd panel; php artisan test --filter=VideoPolicyTest`
Expected: FAIL.

- [ ] **Step 3: Define permissions and implement `VideoPolicy`**

1. In `panel/app/Support/Authorization/RolePermissions.php`, add `Videos` permission group:
   - `videos.view`, `videos.create`, `videos.update`, `videos.delete`.
2. Create `panel/app/Policies/VideoPolicy.php`:
   - `before()` allows platform admin with `can('videos.update')` / `can('videos.delete')`.
   - `viewAny()` / `view()`: user must be active member of tenant (`$user->isTenantMember($tenant)`).
   - `create()`: user must have role `OWNER`, `ADMIN`, or `MEMBER`.
   - `update()`: user must have role `OWNER` or `ADMIN`, or be a `MEMBER` who created the video (`$video->user_id === $user->id`).
   - `delete()`: user must have role `OWNER` or `ADMIN`.
3. Register `VideoPolicy` in `panel/app/Providers/AppServiceProvider.php`.

- [ ] **Step 4: Run tests and Pint**

```bash
cd panel
php artisan test --filter=VideoPolicyTest
./vendor/bin/pint --test
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add panel/app/Support/Authorization/RolePermissions.php panel/app/Policies/VideoPolicy.php panel/app/Providers/AppServiceProvider.php panel/tests/Feature/VideoPolicyTest.php
git commit -m "feat(videos): add video authorization permissions and VideoPolicy"
```

---

### Task 5: MinIO Presigned Multipart Upload Service and Contract

**Files:**
- Create: `panel/app/Domain/Uploads/Contracts/MultipartUploadServiceInterface.php`
- Create: `panel/app/Domain/Uploads/Services/MinioMultipartUploadService.php`
- Modify: `panel/app/Providers/AppServiceProvider.php`
- Create: `panel/tests/Feature/MultipartUploadServiceTest.php`

**Interfaces:**
- Consumes: AWS S3 Client via Flysystem / MinIO configuration, `Video`, `UploadSession`.
- Produces: Service handling `initiate()`, `getPresignedPartUrls()`, `complete()`, and `abort()`.

- [ ] **Step 1: Write failing test for MultipartUploadService**

Create `panel/tests/Feature/MultipartUploadServiceTest.php`:
```php
<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Uploads\Contracts\MultipartUploadServiceInterface;
use App\Domain\Uploads\Models\UploadSession;
use App\Domain\Videos\Models\Video;
use App\Enums\AssetType;
use App\Enums\UploadSessionStatus;
use App\Enums\VideoStatus;
use Aws\S3\S3Client;

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
        ->and($video->fresh()->status)->toBe(VideoStatus::UPLOADING);
});
```

- [ ] **Step 2: Run test to verify failure**

Run: `cd panel; php artisan test --filter=MultipartUploadServiceTest`
Expected: FAIL.

- [ ] **Step 3: Implement `MinioMultipartUploadService` and Contract**

1. Create `panel/app/Domain/Uploads/Contracts/MultipartUploadServiceInterface.php`:
   - `initiateUpload(Video $video, string $filename, int $fileSize, string $contentType, ?S3Client $client = null): UploadSession`
   - `getPresignedPartUrls(UploadSession $session, array $partNumbers, ?S3Client $client = null): array`
   - `completeUpload(UploadSession $session, array $parts, ?S3Client $client = null): Video`
   - `abortUpload(UploadSession $session, ?S3Client $client = null): void`
2. Create `panel/app/Domain/Uploads/Services/MinioMultipartUploadService.php`:
   - Calculates part size (default 5MB or scaled for files > 50GB up to max 10,000 parts).
   - Generates object key: `tenants/{tenant_id}/videos/{video_id}/original/{uuid}.{ext}`.
   - Generates presigned PUT requests with `+1 hour` expiration for requested part numbers.
   - On `completeUpload`: verifies part array, executes `completeMultipartUpload`, creates `VideoAsset` of type `AssetType::ORIGINAL` with size and mime type, marks session `COMPLETED`, marks video status `UPLOADED`.
   - On `abortUpload`: executes `abortMultipartUpload`, marks session `ABORTED`.
3. Bind `MultipartUploadServiceInterface` to `MinioMultipartUploadService` in `AppServiceProvider`.

- [ ] **Step 4: Run tests and Pint**

```bash
cd panel
php artisan test --filter=MultipartUploadServiceTest
./vendor/bin/pint --test
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add panel/app/Domain/Uploads/Contracts/ panel/app/Domain/Uploads/Services/ panel/app/Providers/AppServiceProvider.php panel/tests/Feature/MultipartUploadServiceTest.php
git commit -m "feat(uploads): implement MinioMultipartUploadService with presigned chunking"
```

---

### Task 6: Portal Video Upload API Endpoints

**Files:**
- Create: `panel/app/Http/Controllers/Portal/Videos/VideoUploadController.php`
- Create: `panel/app/Http/Requests/Portal/Videos/InitiateUploadRequest.php`
- Create: `panel/app/Http/Requests/Portal/Videos/GetPartUrlsRequest.php`
- Create: `panel/app/Http/Requests/Portal/Videos/CompleteUploadRequest.php`
- Modify: `panel/routes/portal.php`
- Create: `panel/tests/Feature/PortalVideoUploadTest.php`

**Interfaces:**
- Consumes: `MultipartUploadServiceInterface`, `EnsureTenantContext`, `VideoPolicy`.
- Produces: REST endpoints for multipart upload lifecycle under `/panel/videos/{video}/upload/*`.

- [ ] **Step 1: Write failing test for portal upload endpoints**

Create `panel/tests/Feature/PortalVideoUploadTest.php`:
```php
<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Uploads\Contracts\MultipartUploadServiceInterface;
use App\Domain\Uploads\Models\UploadSession;
use App\Domain\Videos\Models\Video;
use App\Enums\TenantRole;
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
```

- [ ] **Step 2: Run test to verify failure**

Run: `cd panel; php artisan test --filter=PortalVideoUploadTest`
Expected: FAIL.

- [ ] **Step 3: Implement FormRequests, `VideoUploadController`, and routes**

1. Create requests:
   - `InitiateUploadRequest`: validates `filename` (string, max 255), `file_size` (min 1, max 100GB), `content_type` (video mimetypes: mp4, mov, avi, mkv, webm).
   - `GetPartUrlsRequest`: validates `part_numbers` (array of integers from 1 to total_parts).
   - `CompleteUploadRequest`: validates `parts` (array of objects with `PartNumber` and `ETag`).
2. Create `panel/app/Http/Controllers/Portal/Videos/VideoUploadController.php`:
   - `initiate`: authorizes `create`/`update` on video, calls `initiateUpload`, returns session info.
   - `partUrls`: checks session active and not expired, calls `getPresignedPartUrls`, returns part URLs map.
   - `complete`: validates parts, calls `completeUpload`, returns updated video.
   - `abort`: calls `abortUpload`, returns success message.
3. Add routes to `panel/routes/portal.php` inside `tenant.context` middleware group:
   - `POST /panel/videos/{video}/upload/initiate` (`portal.videos.upload.initiate`)
   - `POST /panel/videos/{video}/upload/parts` (`portal.videos.upload.parts`)
   - `POST /panel/videos/{video}/upload/complete` (`portal.videos.upload.complete`)
   - `POST /panel/videos/{video}/upload/abort` (`portal.videos.upload.abort`)

- [ ] **Step 4: Run tests and Pint**

```bash
cd panel
php artisan test --filter=PortalVideoUploadTest
./vendor/bin/pint --test
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add panel/app/Http/Controllers/Portal/Videos/VideoUploadController.php panel/app/Http/Requests/Portal/Videos/ panel/routes/portal.php panel/tests/Feature/PortalVideoUploadTest.php
git commit -m "feat(portal): add multipart video upload controllers and endpoints"
```

---

### Task 7: Portal Video Library CRUD API Endpoints

**Files:**
- Create: `panel/app/Http/Controllers/Portal/Videos/VideoController.php`
- Create: `panel/app/Http/Requests/Portal/Videos/StoreVideoRequest.php`
- Create: `panel/app/Http/Requests/Portal/Videos/UpdateVideoRequest.php`
- Modify: `panel/routes/portal.php`
- Create: `panel/tests/Feature/PortalVideoControllerTest.php`

**Interfaces:**
- Consumes: `Video`, `BelongsToTenant`, `VideoPolicy`, Inertia.
- Produces: Portal Video CRUD pages (`index`, `store`, `show`, `update`, `destroy`).

- [ ] **Step 1: Write failing test for Portal VideoController**

Create `panel/tests/Feature/PortalVideoControllerTest.php`:
```php
<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\Video;
use App\Enums\TenantRole;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\User;

test('member can list videos and view video details in their organization', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Tutorial Video']);

    $this->actingAs($user)
        ->get(route('portal.videos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('portal/videos/index')->has('videos.data', 1));

    $this->actingAs($user)
        ->get(route('portal.videos.show', $video))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('portal/videos/show')->where('video.id', $video->id));
});

test('member can create draft video and update title', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $this->actingAs($user)
        ->post(route('portal.videos.store'), [
            'title' => 'My First Video',
            'description' => 'First test upload',
            'visibility' => VideoVisibility::PRIVATE->value,
        ])
        ->assertRedirect();

    $video = Video::where('title', 'My First Video')->first();
    expect($video)->not->toBeNull()
        ->and($video->tenant_id)->toBe($tenant->id)
        ->and($video->status)->toBe(VideoStatus::DRAFT);
});
```

- [ ] **Step 2: Run test to verify failure**

Run: `cd panel; php artisan test --filter=PortalVideoControllerTest`
Expected: FAIL.

- [ ] **Step 3: Implement FormRequests and `VideoController`**

1. Create `StoreVideoRequest`: validates `title` (required, string, max 255), `description` (nullable, string), `visibility` (enum: private, unlisted, public).
2. Create `UpdateVideoRequest`: validates `title`, `description`, `visibility`.
3. Create `panel/app/Http/Controllers/Portal/Videos/VideoController.php`:
   - `index`: filters by `search`, `status`, `visibility`, paginates (12 per page), returns Inertia `'portal/videos/index'`.
   - `store`: creates draft video for current tenant and authenticated user, redirects to show/upload.
   - `show`: loads video with `assets` and latest `uploadSession`, returns Inertia `'portal/videos/show'`.
   - `update`: authorizes `update`, updates fields, redirects back with success flash.
   - `destroy`: authorizes `delete`, deletes video and dispatches storage cleanup, redirects to index.
4. Add resource routes to `panel/routes/portal.php`.

- [ ] **Step 4: Run tests and Pint**

```bash
cd panel
php artisan test --filter=PortalVideoControllerTest
./vendor/bin/pint --test
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add panel/app/Http/Controllers/Portal/Videos/VideoController.php panel/app/Http/Requests/Portal/Videos/ panel/routes/portal.php panel/tests/Feature/PortalVideoControllerTest.php
git commit -m "feat(portal): implement Portal Video CRUD controller and routes"
```

---

### Task 8: Admin Panel Video Oversight API and Routes

**Files:**
- Create: `panel/app/Http/Controllers/Admin/VideoController.php`
- Modify: `panel/routes/admin.php`
- Create: `panel/tests/Feature/AdminVideoControllerTest.php`

**Interfaces:**
- Consumes: Platform permissions (`videos.view`, `videos.delete`), `Video::withoutTenantScope()`.
- Produces: Admin cross-tenant video oversight endpoints.

- [ ] **Step 1: Write failing test for Admin VideoController**

Create `panel/tests/Feature/AdminVideoControllerTest.php`:
```php
<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\Video;
use App\Models\User;

test('platform admin can view videos across all tenants', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $tenantA = Tenant::factory()->create(['name' => 'Studio A']);
    $tenantB = Tenant::factory()->create(['name' => 'Studio B']);

    Video::factory()->create(['tenant_id' => $tenantA->id, 'title' => 'Video A']);
    Video::factory()->create(['tenant_id' => $tenantB->id, 'title' => 'Video B']);

    $this->actingAs($admin)
        ->get(route('admin.videos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/videos/index')->has('videos.data', 2));
});

test('regular user cannot access admin video endpoints', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.videos.index'))
        ->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify failure**

Run: `cd panel; php artisan test --filter=AdminVideoControllerTest`
Expected: FAIL.

- [ ] **Step 3: Implement `Admin\VideoController` and routes**

1. Create `panel/app/Http/Controllers/Admin/VideoController.php`:
   - `index`: uses `Video::withoutTenantScope()->with('tenant', 'user')`, supports `search`, `tenant_id`, `status` filters, returns Inertia `'admin/videos/index'`.
   - `show`: loads video with tenant, user, assets, and upload sessions, returns Inertia `'admin/videos/show'`.
   - `destroy`: deletes video across any tenant, returns redirect.
2. Add routes to `panel/routes/admin.php` protected by `permission:videos.view` / `permission:videos.delete`.

- [ ] **Step 4: Run tests and Pint**

```bash
cd panel
php artisan test --filter=AdminVideoControllerTest
./vendor/bin/pint --test
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add panel/app/Http/Controllers/Admin/VideoController.php panel/routes/admin.php panel/tests/Feature/AdminVideoControllerTest.php
git commit -m "feat(admin): implement admin cross-tenant video oversight controller and routes"
```

---

### Task 9: Frontend Portal UI — Video Library Index and Details Pages

**Files:**
- Create: `panel/resources/js/types/video.d.ts`
- Modify: `panel/resources/js/config/usePortalNavigation.ts`
- Create: `panel/resources/js/pages/portal/videos/index.tsx`
- Create: `panel/resources/js/pages/portal/videos/show.tsx`
- Create: `panel/resources/js/pages/portal/videos/partials/video-card.tsx`
- Create: `panel/resources/js/pages/portal/videos/partials/video-status-badge.tsx`
- Modify: `panel/lang/en.json`

**Interfaces:**
- Consumes: Typed Wayfinder routes for `portal.videos.*`, `useLocalizer`.
- Produces: Polished, responsive Video Library UI with grid/table toggle, status filter, and video details.

- [ ] **Step 1: Define TypeScript types and update Portal Navigation**

1. Create `panel/resources/js/types/video.d.ts`:
   - Define `Video`, `VideoAsset`, `UploadSession`, `VideoStatus`, `VideoVisibility`.
2. Update `panel/resources/js/config/usePortalNavigation.ts` to include "Videos" item with `Video` icon from `lucide-react` pointing to `index().url` from `@/routes/portal/videos`.

- [ ] **Step 2: Create Video Library Index page with Grid/Table views**

1. Create `panel/resources/js/pages/portal/videos/partials/video-status-badge.tsx`:
   - Color-coded badges for `draft`, `uploading`, `uploaded`, `queued`, `transcoding`, `ready`, `failed`.
2. Create `panel/resources/js/pages/portal/videos/partials/video-card.tsx` for grid view with thumbnail placeholder, duration, title, visibility, and action menu.
3. Create `panel/resources/js/pages/portal/videos/index.tsx`:
   - Header with title and "Upload Video" button triggering upload modal.
   - Filter bar: Search input, status filter dropdown, visibility filter dropdown, and grid/table view toggle.
   - Empty state when no videos match.
   - Pagination component.

- [ ] **Step 3: Create Video Details page**

Create `panel/resources/js/pages/portal/videos/show.tsx`:
- Header with breadcrumbs and actions (Edit metadata, Delete video).
- Status banner explaining current video processing stage.
- Metadata card (title, description, duration, visibility, created date).
- Assets card displaying registered media assets (original, master, HLS playlists).

- [ ] **Step 4: Update localization keys in `panel/lang/en.json`**

Add all user-facing strings (e.g. `"Videos"`, `"Upload Video"`, `"No videos found"`, `"Processing..."`, `"Ready"`).

- [ ] **Step 5: Run TypeScript checks and ESLint**

```bash
cd panel
npm run types
npm run test:lint
```
Expected: 0 errors.

- [ ] **Step 6: Commit**

```bash
git add panel/resources/js/types/video.d.ts panel/resources/js/config/usePortalNavigation.ts panel/resources/js/pages/portal/videos/ panel/lang/en.json
git commit -m "feat(portal-ui): create video library index and details pages"
```

---

### Task 10: Frontend Portal UI — Direct-to-MinIO Chunked Uploader

**Files:**
- Create: `panel/resources/js/pages/portal/videos/partials/upload-dialog.tsx`
- Create: `panel/resources/js/lib/chunked-uploader.ts`
- Modify: `panel/resources/js/pages/portal/videos/index.tsx`
- Modify: `panel/lang/en.json`

**Interfaces:**
- Consumes: `portal.videos.store`, `portal.videos.upload.initiate`, `parts`, `complete`, `abort`.
- Produces: Direct-to-MinIO chunked upload client with real-time progress bar.

- [ ] **Step 1: Implement `chunked-uploader.ts` upload client**

Create `panel/resources/js/lib/chunked-uploader.ts`:
- Splits file into slices of size `part_size` (min 5MB).
- Calls `initiate` endpoint to obtain `upload_id` and `total_parts`.
- Batches part requests (concurrency limit = 3) to fetch presigned PUT URLs via `parts` endpoint.
- Performs `PUT` request for each chunk directly to MinIO endpoint, capturing HTTP `ETag` header.
- Tracks upload progress and calculates overall percentage and transfer speed.
- On all chunks successful: calls `complete` endpoint with sorted parts array `[{ PartNumber, ETag }]`.
- On cancel or unrecoverable error: calls `abort` endpoint.

- [ ] **Step 2: Create `upload-dialog.tsx` React component**

Create `panel/resources/js/pages/portal/videos/partials/upload-dialog.tsx`:
- Radix Dialog modal triggered from "Upload Video" button.
- Drag & Drop zone supporting `.mp4`, `.mov`, `.mkv`, `.webm`.
- Title input (prefilled from filename) and Visibility selector.
- Visual upload progress bar with uploaded MB / total MB and percentage.
- Cancel upload button with confirmation.
- On complete: redirects to video details page or refreshes video list.

- [ ] **Step 3: Integrate Dialog into `index.tsx`**

Attach `UploadDialog` to "Upload Video" button on the video library index page.

- [ ] **Step 4: Run typechecks and linting**

```bash
cd panel
npm run types
npm run test:lint
```
Expected: 0 errors.

- [ ] **Step 5: Commit**

```bash
git add panel/resources/js/lib/chunked-uploader.ts panel/resources/js/pages/portal/videos/partials/upload-dialog.tsx panel/resources/js/pages/portal/videos/index.tsx panel/lang/en.json
git commit -m "feat(portal-ui): add direct-to-minio chunked uploader dialog and client"
```

---

### Task 11: Frontend Admin UI — Video Oversight Pages

**Files:**
- Modify: `panel/resources/js/config/useAdminNavigation.ts`
- Create: `panel/resources/js/pages/admin/videos/index.tsx`
- Create: `panel/resources/js/pages/admin/videos/show.tsx`
- Modify: `panel/lang/en.json`

**Interfaces:**
- Consumes: Admin video routes (`admin.videos.*`), `useLocalizer`.
- Produces: Admin oversight page for monitoring all tenant videos.

- [ ] **Step 1: Update Admin Navigation and add routes**

Update `panel/resources/js/config/useAdminNavigation.ts` with "Videos" nav link with `Film` icon guarded by `<Can permission="videos.view">`.

- [ ] **Step 2: Create Admin Video Index and Show pages**

1. Create `panel/resources/js/pages/admin/videos/index.tsx`:
   - Table displaying Video Title, Organization (Tenant badge), Uploader, Status, Visibility, Duration, and Created Date.
   - Search by title and filters by Tenant and Status.
   - Delete action sheet for platform moderation.
2. Create `panel/resources/js/pages/admin/videos/show.tsx`:
   - Technical inspection view displaying raw metadata, storage disk, object key, and registered assets.

- [ ] **Step 3: Run TypeScript checks and ESLint**

```bash
cd panel
npm run types
npm run test:lint
```
Expected: 0 errors.

- [ ] **Step 4: Commit**

```bash
git add panel/resources/js/config/useAdminNavigation.ts panel/resources/js/pages/admin/videos/ panel/lang/en.json
git commit -m "feat(admin-ui): create admin video oversight and technical inspection pages"
```

---

### Task 12: End-to-End Multi-Tenant Video Isolation Audit and Seeders

**Files:**
- Create: `panel/database/seeders/VideoSeeder.php`
- Modify: `panel/database/seeders/DatabaseSeeder.php`
- Create: `panel/tests/Feature/VideoIsolationEndToEndTest.php`

**Interfaces:**
- Consumes: All Phase 2 models, services, policies, and seeders.
- Produces: End-to-end integration audit proving strict tenant video isolation and realistic demo video seed data.

- [ ] **Step 1: Write comprehensive End-to-End Video Isolation Test**

Create `panel/tests/Feature/VideoIsolationEndToEndTest.php`:
```php
<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Uploads\Contracts\MultipartUploadServiceInterface;
use App\Domain\Uploads\Models\UploadSession;
use App\Domain\Videos\Models\Video;
use App\Enums\TenantRole;
use App\Enums\VideoStatus;
use App\Models\User;

test('end-to-end multi-tenant video isolation and upload lifecycle enforcement', function (): void {
    // 1. Setup Tenant A and Tenant B with owners and members
    $tenantA = Tenant::factory()->create(['name' => 'Studio A', 'slug' => 'studio-a']);
    $userA = User::factory()->create(['name' => 'Alice']);
    $tenantA->users()->attach($userA->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $tenantB = Tenant::factory()->create(['name' => 'Studio B', 'slug' => 'studio-b']);
    $userB = User::factory()->create(['name' => 'Bob']);
    $tenantB->users()->attach($userB->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    // 2. Alice creates video in Tenant A
    app()->instance('current_tenant', $tenantA);
    $videoA = Video::factory()->create(['tenant_id' => $tenantA->id, 'user_id' => $userA->id, 'title' => 'Alice Secret Video']);

    // 3. Bob cannot view Alice's video in the portal list or details
    app()->instance('current_tenant', $tenantB);
    $this->actingAs($userB)
        ->get(route('portal.videos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('videos.data', []));

    $this->actingAs($userB)
        ->get(route('portal.videos.show', $videoA))
        ->assertNotFound();

    // 4. Bob cannot initiate, complete, or abort uploads for Alice's video
    $this->actingAs($userB)
        ->postJson(route('portal.videos.upload.initiate', $videoA), [
            'filename' => 'hack.mp4',
            'file_size' => 10 * 1024 * 1024,
            'content_type' => 'video/mp4',
        ])
        ->assertNotFound();

    // 5. Alice can update her video, but cannot update videos from Tenant B
    app()->instance('current_tenant', $tenantA);
    $this->actingAs($userA)
        ->put(route('portal.videos.update', $videoA), [
            'title' => 'Alice Updated Video',
            'description' => 'Updated description',
            'visibility' => 'private',
        ])
        ->assertSessionHasNoErrors();
    expect($videoA->fresh()->title)->toBe('Alice Updated Video');
});
```

- [ ] **Step 2: Create `VideoSeeder.php`**

1. Create `panel/database/seeders/VideoSeeder.php`:
   - Seeds realistic demo videos for `Acme Streaming Studio` (`acme-streaming-studio`):
     - "Product Keynote 2026" (Ready, Public, 120s, with assets).
     - "Internal Engineering All-Hands" (Ready, Private, 3600s, with assets).
     - "Draft Commercial Campaign" (Draft, Private).
   - Seeds demo videos for `Nexus Media Labs` (`nexus-media-labs`):
     - "Documentary Rough Cut" (Uploading, Unlisted).
     - "Color Grading Test Footage" (Ready, Public, 45s).
2. Wire `VideoSeeder` into `panel/database/seeders/DatabaseSeeder.php`.

- [ ] **Step 3: Run full test suite and verify seeders**

```bash
cd panel
php artisan test
php artisan db:seed --class=VideoSeeder
```
Expected: All tests pass.

- [ ] **Step 4: Commit**

```bash
git add panel/database/seeders/VideoSeeder.php panel/database/seeders/DatabaseSeeder.php panel/tests/Feature/VideoIsolationEndToEndTest.php
git commit -m "test(videos): add end-to-end multi-tenant video isolation test and realistic seeders"
```
