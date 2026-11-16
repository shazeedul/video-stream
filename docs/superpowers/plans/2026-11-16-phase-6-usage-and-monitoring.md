# Phase 6: Usage, Monitoring, and Production Readiness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement tenant storage quota tracking, low-overhead playback egress metering, multi-worker health monitoring, administrative observability dashboards, customer usage views, and disaster recovery automation.

**Architecture:** Tenant storage is aggregated from `VideoAsset` records and in-flight `UploadSession` sizes, enforcing quotas during upload initiation (`/upload/initiate`) while allowing soft-limit completion for transcoding outputs. Playback views and streaming egress are tracked via sub-millisecond atomic Redis counters triggered during Nginx `auth_request` subrequests and flushed hourly to PostgreSQL. Health checks decouple public web liveness (`/health`) from deep infrastructure diagnostics (`/api/v1/admin/health`), and Go media workers broadcast instance-specific heartbeats with automated registry pruning.

**Tech Stack:** PHP 8.4, Laravel 11/13, PostgreSQL 16, Redis 7, MinIO, RabbitMQ 3, Go 1.23, React 19, TypeScript, Tailwind CSS v4, Inertia.js v2, Docker Compose.

**Spec:** [docs/superpowers/specs/2026-11-16-phase-6-usage-and-monitoring-design.md](file:///D:/Playground/wiz-video-platform/docs/superpowers/specs/2026-11-16-phase-6-usage-and-monitoring-design.md)

---

## Global Constraints

- **PHP Standards:** `declare(strict_types=1);` and `final class` on all new PHP domain services, controllers, and commands; 100% clean Laravel Pint (`./vendor/bin/pint --test`).
- **Go Standards:** Idiomatic Go 1.23+, standard directory layout (`internal/`), structured logging with `slog`, context-aware graceful shutdown.
- **TypeScript / React Standards:** Strict TypeScript compilation (`npm run types` 0 errors), React 19 functional components, Tailwind CSS styling.
- **Bucket Names:** Strictly align with `config/filesystems.php`: `videos-original`, `videos-processed`, `thumbnails`.
- **Tenancy Boundary:** Tenant usage records and customer portal views must strictly adhere to tenant context isolation.
- **Zero Web Outage Cascades:** Background worker or RabbitMQ downtime must never cause public `/health` to return 503.

---

## Review Focus

1. **Concurrent Upload Quota Race Condition:** Two concurrent `/upload/initiate` requests that together exceed quota must not both succeed; the second request must fail with 422 Unprocessable Entity.
2. **Repeated HLS Segment Polling Double-Count:** Repeated requests for `master.m3u8` within a 30-minute deduplication window must increment view counts exactly once.
3. **Partial Infrastructure Outage Degradation:** When RabbitMQ or worker heartbeats are down, public `/health` must return HTTP 200 with `status: degraded` while `/api/v1/admin/health` reports the exact component failure.
4. **Worker Graceful Shutdown & Multi-Instance Heartbeat Pruning:** Clean termination of one worker daemon removes only its instance ID from `workers:active` without affecting other active workers or marking the worker pool down.
5. **Soft Quota Transcoding Completion:** A video transcoding callback that causes tenant storage to exceed quota must succeed with HTTP 200 and transition video to `READY`, while subsequent upload initiations are blocked.

---

### Task 1: Tenant Usage Schema, Models & Storage Metering Service

**Files:**
- Create: `panel/database/migrations/2026_11_16_000001_create_tenant_usage_records_table.php`
- Create: `panel/app/Domain/Usage/Models/TenantUsageRecord.php`
- Create: `panel/app/Domain/Usage/Contracts/TenantUsageServiceInterface.php`
- Create: `panel/app/Domain/Usage/Services/TenantUsageService.php`
- Modify: `panel/app/Domain/Tenants/Models/Tenant.php`
- Modify: `panel/app/Providers/AppServiceProvider.php`
- Create: `panel/tests/Unit/Domain/Usage/TenantUsageServiceTest.php`

**Interfaces:**
- Produces: `TenantUsageRecord` model and `TenantUsageServiceInterface` with:
  - `getCurrentStorageBytes(Tenant $tenant): int`
  - `getStorageQuotaBytes(Tenant $tenant): int`
  - `hasExceededStorageQuota(Tenant $tenant, int $additionalBytes = 0): bool`
  - `recalculateTenantStorage(Tenant $tenant): int`

- [ ] **Step 1: Write failing unit tests for TenantUsageService**

Create `panel/tests/Unit/Domain/Usage/TenantUsageServiceTest.php`:
- Test calculation sums `VideoAsset` records for the tenant.
- Test calculation includes active `UploadSession` records (`status: uploading`).
- Test calculation ignores soft-deleted or other tenants' assets.
- Test `hasExceededStorageQuota` returns true when storage + additional exceeds quota.
- Test default quota (10 GB) applies when `tenants.settings->storage_quota_bytes` is null.

- [ ] **Step 2: Run test to verify failure**

Run: `docker compose exec -T panel php artisan test tests/Unit/Domain/Usage/TenantUsageServiceTest.php`  
Expected: FAIL (migration and classes do not exist).

- [ ] **Step 3: Create migration for `tenant_usage_records`**

Create `panel/database/migrations/2026_11_16_000001_create_tenant_usage_records_table.php`:
- Columns: `id` (uuid primary), `tenant_id` (uuid, indexed foreign key), `date` (date), `storage_bytes` (unsigned bigInteger, default 0), `egress_bytes` (unsigned bigInteger, default 0), `view_count` (unsigned bigInteger, default 0), `video_count` (unsigned integer, default 0), timestamps.
- Composite unique index on `['tenant_id', 'date']`.

- [ ] **Step 4: Create TenantUsageRecord model and update Tenant model**

1. Create `panel/app/Domain/Usage/Models/TenantUsageRecord.php`:
   - Extends `Model`, uses `HasUuids`, `BelongsToTenant`.
   - Fillable: `['tenant_id', 'date', 'storage_bytes', 'egress_bytes', 'view_count', 'video_count']`.
   - Cast `date` to `immutable_date`, integer bytes to integers.
2. In `panel/app/Domain/Tenants/Models/Tenant.php`:
   - Add method `getStorageQuotaBytes(): int` returning `(int) ($this->settings['storage_quota_bytes'] ?? 10 * 1024 * 1024 * 1024)`.
   - Add method `usageRecords(): HasMany` returning `TenantUsageRecord::class`.

- [ ] **Step 5: Implement TenantUsageServiceInterface & TenantUsageService**

1. Create `panel/app/Domain/Usage/Contracts/TenantUsageServiceInterface.php`:
```php
<?php

declare(strict_types=1);

namespace App\Domain\Usage\Contracts;

use App\Domain\Tenants\Models\Tenant;

interface TenantUsageServiceInterface
{
    public function getCurrentStorageBytes(Tenant $tenant): int;

    public function getStorageQuotaBytes(Tenant $tenant): int;

    public function hasExceededStorageQuota(Tenant $tenant, int $additionalBytes = 0): bool;

    public function recalculateTenantStorage(Tenant $tenant): int;
}
```

2. Create `panel/app/Domain/Usage/Services/TenantUsageService.php`:
   - Sums `size_bytes` from `VideoAsset` where `video.tenant_id == $tenant->id`.
   - Sums estimated bytes from active `UploadSession` where `status == PENDING` or `UPLOADING` (`part_size * total_parts`).
   - Evaluates quota against `getStorageQuotaBytes($tenant)`.

3. Bind `TenantUsageServiceInterface` to `TenantUsageService` in `panel/app/Providers/AppServiceProvider.php`.

- [ ] **Step 6: Run tests and verify Pint**

Run:
```bash
docker compose exec -T panel php artisan migrate
docker compose exec -T panel php artisan test tests/Unit/Domain/Usage/TenantUsageServiceTest.php
docker compose exec -T panel ./vendor/bin/pint --test
```
Expected: PASS (100% tests pass, 0 Pint violations).

- [ ] **Step 7: Commit**

```bash
git add panel/database/migrations/ panel/app/Domain/Usage/ panel/app/Domain/Tenants/Models/Tenant.php panel/app/Providers/AppServiceProvider.php panel/tests/Unit/Domain/Usage/
git commit -m "feat(usage): implement tenant usage records schema and storage metering service"
```

---

### Task 2: Upload Quota Guard & Enforcement in Upload Lifecycle

**Files:**
- Modify: `panel/app/Domain/Usage/Contracts/TenantUsageServiceInterface.php`
- Modify: `panel/app/Domain/Usage/Services/TenantUsageService.php`
- Modify: `panel/app/Http/Controllers/Portal/Videos/VideoUploadController.php`
- Create: `panel/tests/Feature/Portal/Videos/UploadQuotaEnforcementTest.php`

**Interfaces:**
- Consumes: `TenantUsageServiceInterface::hasExceededStorageQuota(Tenant $tenant, int $additionalBytes)`.
- Produces: HTTP 422 Unprocessable Entity with error message when upload would exceed tenant quota.

- [ ] **Step 1: Write failing feature test for upload quota enforcement**

Create `panel/tests/Feature/Portal/Videos/UploadQuotaEnforcementTest.php`:
- Test initiating upload within quota returns 200 OK with upload ID.
- Test initiating upload exceeding quota returns 422 with validation error "Tenant storage quota exceeded".
- Test concurrent upload sessions: existing in-flight upload + new upload exceeding quota is rejected.
- Test aborting an upload session releases reserved storage and allows new upload.
- Test soft quota on transcoding callback: `POST /api/v1/internal/processing/callback` completes successfully even if resulting assets exceed quota.

- [ ] **Step 2: Run test to verify failure**

Run: `docker compose exec -T panel php artisan test tests/Feature/Portal/Videos/UploadQuotaEnforcementTest.php`  
Expected: FAIL (quota check not enforced in controller).

- [ ] **Step 3: Update VideoUploadController to enforce storage quota**

1. In `panel/app/Http/Controllers/Portal/Videos/VideoUploadController.php`:
   - Inject `TenantUsageServiceInterface $usageService` in constructor.
   - In `initiate()`:
     ```php
     $fileSize = (int) $request->validated('file_size');
     if ($video->tenant !== null && $this->usageService->hasExceededStorageQuota($video->tenant, $fileSize)) {
         return response()->json([
             'message' => 'Tenant storage quota exceeded. Please upgrade your plan or remove existing media.',
         ], 422);
     }
     ```
   - In `abort()`: ensure `$session->update(['status' => UploadSessionStatus::ABORTED])` cleans up in-flight storage state.

- [ ] **Step 4: Run tests and verify Pint**

Run:
```bash
docker compose exec -T panel php artisan test tests/Feature/Portal/Videos/UploadQuotaEnforcementTest.php
docker compose exec -T panel ./vendor/bin/pint --test
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add panel/app/Http/Controllers/Portal/Videos/VideoUploadController.php panel/tests/Feature/Portal/Videos/UploadQuotaEnforcementTest.php
git commit -m "feat(uploads): enforce tenant storage quotas during upload initiation"
```

---

### Task 3: Low-Overhead Playback Egress & View Metering in Redis

**Files:**
- Modify: `panel/app/Domain/Usage/Contracts/TenantUsageServiceInterface.php`
- Modify: `panel/app/Domain/Usage/Services/TenantUsageService.php`
- Modify: `panel/app/Http/Controllers/Api/Internal/PlaybackAuthController.php`
- Create: `panel/app/Console/Commands/AggregateUsageCommand.php`
- Modify: `panel/routes/console.php`
- Create: `panel/tests/Feature/Api/Internal/PlaybackUsageTrackingTest.php`
- Create: `panel/tests/Feature/Commands/AggregateUsageCommandTest.php`

**Interfaces:**
- Produces:
  - `recordPlaybackView(string $tenantId, string $videoId, string $clientIdentifier): bool`
  - `recordPlaybackEgress(string $tenantId, int $bytes): void`
  - `aggregateHourlyUsage(?string $date = null): int`
  - Artisan command `usage:aggregate-bandwidth`

- [ ] **Step 1: Write failing tests for Redis playback usage tracking**

Create `panel/tests/Feature/Api/Internal/PlaybackUsageTrackingTest.php`:
- Test requesting `master.m3u8` records 1 view in Redis.
- Test repeated requests for `master.m3u8` with the same token/IP within 30 minutes does NOT increment view count again.
- Test requesting `.ts` segment records 1,572,864 bytes egress in Redis.
- Test non-ready video or 403 request does NOT increment views or egress.

Create `panel/tests/Feature/Commands/AggregateUsageCommandTest.php`:
- Test running `php artisan usage:aggregate-bandwidth` persists Redis views and egress into `tenant_usage_records`.
- Test running aggregation command twice idempotently updates existing record for the date.

- [ ] **Step 2: Run test to verify failure**

Run: `docker compose exec -T panel php artisan test tests/Feature/Api/Internal/PlaybackUsageTrackingTest.php`  
Expected: FAIL.

- [ ] **Step 3: Implement Redis tracking methods in TenantUsageService**

In `panel/app/Domain/Usage/Services/TenantUsageService.php`:
1. `recordPlaybackView(string $tenantId, string $videoId, string $clientIdentifier): bool`:
   - Compute hash: `md5("{$videoId}:{$clientIdentifier}")`.
   - Redis deduplication key: `usage:view_dedup:{$hash}` with TTL 1800s (30 mins).
   - If `Redis::set($dedupKey, '1', 'EX', 1800, 'NX')`:
     - Increment `tenant:{$tenantId}:views:{date}`.
     - Return true.
   - Return false.
2. `recordPlaybackEgress(string $tenantId, int $bytes): void`:
   - Increment `tenant:{$tenantId}:egress:{date}` by `$bytes`.
3. `aggregateHourlyUsage(?string $date = null): int`:
   - Iterates through active tenants, reads Redis counters for `$date ?? date('Y-m-d')`, updates or creates `TenantUsageRecord`, and recalculates current storage.

- [ ] **Step 4: Update PlaybackAuthController & Schedule Artisan Command**

1. In `panel/app/Http/Controllers/Api/Internal/PlaybackAuthController.php`:
   - Inject `TenantUsageServiceInterface $usageService`.
   - Inspect `$originalUri = (string) $request->header('X-Original-URI', '');`.
   - Upon successful authorization (HTTP 200):
     - If `str_contains($originalUri, 'master.m3u8')`:
       - Call `$usageService->recordPlaybackView((string) $video->tenant_id, (string) $video->id, (string) ($token ?? $request->ip()));`.
     - If `str_ends_with($originalUri, '.ts')` or `str_contains($originalUri, '.ts?')`:
       - Call `$usageService->recordPlaybackEgress((string) $video->tenant_id, 1572864);`.
2. Create `panel/app/Console/Commands/AggregateUsageCommand.php`:
   - Signature: `usage:aggregate-bandwidth {--date= : The date to aggregate (YYYY-MM-DD)}`.
   - Calls `$usageService->aggregateHourlyUsage($date)`.
3. Register hourly schedule in `panel/routes/console.php`:
   - `Schedule::command('usage:aggregate-bandwidth')->hourly();`

- [ ] **Step 5: Run tests and verify Pint**

Run:
```bash
docker compose exec -T panel php artisan test tests/Feature/Api/Internal/PlaybackUsageTrackingTest.php
docker compose exec -T panel php artisan test tests/Feature/Commands/AggregateUsageCommandTest.php
docker compose exec -T panel ./vendor/bin/pint --test
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add panel/app/Domain/Usage/ panel/app/Http/Controllers/Api/Internal/PlaybackAuthController.php panel/app/Console/Commands/ panel/routes/console.php panel/tests/Feature/
git commit -m "feat(usage): implement low-overhead redis playback views and egress metering"
```

---

### Task 4: Go Media Worker Multi-Instance Heartbeat Registry & Laravel Ingress

**Files:**
- Create: `worker/internal/observability/heartbeat.go`
- Create: `worker/internal/observability/heartbeat_test.go`
- Modify: `worker/internal/config/config.go`
- Modify: `worker/cmd/worker/main.go`
- Create: `panel/app/Http/Controllers/Api/Internal/WorkerHeartbeatController.php`
- Modify: `panel/routes/api/v1.php`
- Create: `panel/tests/Feature/Api/Internal/WorkerHeartbeatTest.php`

**Interfaces:**
- Produces:
  - Go worker periodic heartbeat publisher (every 15s) with unique `worker_id`.
  - HTTP `POST /api/v1/internal/worker/heartbeat` with `X-Internal-Secret` protection.
  - Redis heartbeat registry: `workers:active` (Set) and `worker:heartbeat:{id}` (String with 45s TTL).

- [ ] **Step 1: Write failing feature test in Laravel for WorkerHeartbeatController**

Create `panel/tests/Feature/Api/Internal/WorkerHeartbeatTest.php`:
- Test valid heartbeat request with matching `X-Internal-Secret` registers worker in Redis with 45s TTL and returns 200 OK.
- Test missing or invalid `X-Internal-Secret` returns 403 Forbidden.
- Test worker shutdown payload (`status: stopped`) removes worker from `workers:active` and deletes key.

- [ ] **Step 2: Run test to verify failure**

Run: `docker compose exec -T panel php artisan test tests/Feature/Api/Internal/WorkerHeartbeatTest.php`  
Expected: FAIL.

- [ ] **Step 3: Implement WorkerHeartbeatController in Laravel**

1. Create `panel/app/Http/Controllers/Api/Internal/WorkerHeartbeatController.php`:
   - Validates JSON: `worker_id` (string, required), `hostname` (string, required), `status` (string, 'active'|'stopped').
   - If `status === 'active'`:
     - `Redis::setex("worker:heartbeat:{$workerId}", 45, json_encode(['id' => $workerId, 'hostname' => $hostname, 'timestamp' => time()]));`
     - `Redis::sadd('workers:active', $workerId);`
   - If `status === 'stopped'`:
     - `Redis::srem('workers:active', $workerId);`
     - `Redis::del("worker:heartbeat:{$workerId}");`
   - Returns `response()->json(['status' => 'recorded'], 200)`.
2. Register in `panel/routes/api/v1.php` under `VerifyInternalSecret` middleware:
   `Route::post('/worker/heartbeat', WorkerHeartbeatController::class)->name('internal.worker.heartbeat');`

- [ ] **Step 4: Implement Heartbeat Publisher in Go Worker**

1. Create `worker/internal/observability/heartbeat.go`:
   - Generates unique ID: `fmt.Sprintf("worker-%s-%s", hostname, uuid.NewString()[:8])`.
   - Starts ticker every 15s sending `POST /api/v1/internal/worker/heartbeat` with `X-Internal-Secret`.
   - On context cancellation (SIGINT/SIGTERM): sends `status: "stopped"` with 2s timeout.
2. In `worker/cmd/worker/main.go`:
   - Launch heartbeat publisher in background goroutine with worker context.
3. Write unit tests in `worker/internal/observability/heartbeat_test.go` verifying payload formatting and stop signal handling with mock HTTP server.

- [ ] **Step 5: Verify Go and Laravel tests**

Run:
```bash
docker compose exec -T panel php artisan test tests/Feature/Api/Internal/WorkerHeartbeatTest.php
docker compose exec -T panel ./vendor/bin/pint --test
docker run --rm -v "D:\Playground\wiz-video-platform\worker:/app" -w /app golang:1.23-alpine go test -v ./internal/observability/...
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add worker/internal/observability/ worker/cmd/worker/main.go panel/app/Http/Controllers/Api/Internal/WorkerHeartbeatController.php panel/routes/api/v1.php panel/tests/Feature/Api/Internal/WorkerHeartbeatTest.php
git commit -m "feat(monitoring): implement multi-instance worker heartbeat publisher and registry"
```

---

### Task 5: Health Check Service, Resilient Liveness & Deep Diagnostic Probes

**Files:**
- Create: `panel/app/Domain/Monitoring/Contracts/HealthCheckServiceInterface.php`
- Create: `panel/app/Domain/Monitoring/Services/HealthCheckService.php`
- Create: `panel/app/Domain/Monitoring/DataTransferObjects/HealthReport.php`
- Create: `panel/app/Http/Controllers/HealthCheckController.php`
- Modify: `panel/app/Providers/AppServiceProvider.php`
- Modify: `panel/routes/web.php`
- Modify: `panel/routes/api/v1.php`
- Create: `panel/tests/Feature/HealthCheckTest.php`

**Interfaces:**
- Produces:
  - `GET /health` (Public liveness probe: 200 OK or 503 Service Unavailable).
  - `GET /api/v1/admin/health` (Deep diagnostics with components: DB, Redis, MinIO buckets, RabbitMQ, Workers).

- [ ] **Step 1: Write failing tests for HealthCheck endpoints**

Create `panel/tests/Feature/HealthCheckTest.php`:
- Test `/health` returns 200 OK with `{"status": "ok"}` when DB and Redis are connected.
- Test `/health` returns 503 when Database connection throws exception.
- Test `/health` returns 200 OK with `{"status": "degraded"}` when RabbitMQ or Go worker is offline (preventing container evictions).
- Test `/api/v1/admin/health` requires authentication and permission `admin.panel`.
- Test `/api/v1/admin/health` returns detailed JSON with latencies for DB, Redis, MinIO buckets (`videos-original`, `videos-processed`, `thumbnails`), RabbitMQ, and active worker count.

- [ ] **Step 2: Run test to verify failure**

Run: `docker compose exec -T panel php artisan test tests/Feature/HealthCheckTest.php`  
Expected: FAIL.

- [ ] **Step 3: Implement HealthCheckService & HealthReport DTO**

1. Create `panel/app/Domain/Monitoring/DataTransferObjects/HealthReport.php`:
   - Properties: `status` ('healthy'|'degraded'|'unhealthy'), `timestamp`, `components` (array).
2. Create `panel/app/Domain/Monitoring/Services/HealthCheckService.php`:
   - `checkLiveness(): array`: Checks PostgreSQL (`DB::connection()->getPdo()`) and Redis (`Redis::ping()`).
   - `checkDiagnostics(): HealthReport`:
     - PostgreSQL: measures query latency for `SELECT 1`.
     - Redis: measures `PING` latency.
     - MinIO: verifies bucket existence for `videos-original`, `videos-processed`, and `thumbnails`.
     - RabbitMQ: checks AMQP socket connection and inspects `media.jobs` queue depth.
     - Workers: reads `workers:active` from Redis; checks key TTLs; prunes expired workers; reports active count.
3. Bind `HealthCheckServiceInterface` in `panel/app/Providers/AppServiceProvider.php`.

- [ ] **Step 4: Create HealthCheckController & Register Routes**

1. Create `panel/app/Http/Controllers/HealthCheckController.php`:
   - `liveness()`: returns 200 or 503 based on `checkLiveness()`.
   - `diagnostics()`: returns full JSON from `checkDiagnostics()`.
2. In `panel/routes/web.php`:
   - `Route::get('/health', [HealthCheckController::class, 'liveness'])->name('health');`
3. In `panel/routes/api/v1.php`:
   - Under `['auth:sanctum', 'verified', 'admin.panel']`:
     `Route::get('/admin/health', [HealthCheckController::class, 'diagnostics'])->name('admin.health');`

- [ ] **Step 5: Run tests and verify Pint**

Run:
```bash
docker compose exec -T panel php artisan test tests/Feature/HealthCheckTest.php
docker compose exec -T panel ./vendor/bin/pint --test
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add panel/app/Domain/Monitoring/ panel/app/Http/Controllers/HealthCheckController.php panel/routes/ panel/tests/Feature/HealthCheckTest.php
git commit -m "feat(monitoring): implement resilient liveness probe and deep administrative diagnostics"
```

---

### Task 6: Admin System Monitoring Dashboard & Customer Portal Usage UI

**Files:**
- Create: `panel/app/Http/Controllers/Admin/MonitoringController.php`
- Create: `panel/app/Http/Controllers/Portal/Settings/UsageController.php`
- Modify: `panel/routes/admin.php`
- Modify: `panel/routes/portal.php`
- Create: `panel/resources/js/types/monitoring.ts`
- Create: `panel/resources/js/types/usage.ts`
- Create: `panel/resources/js/pages/admin/monitoring/index.tsx`
- Create: `panel/resources/js/pages/portal/settings/usage.tsx`
- Create: `panel/tests/Feature/Admin/MonitoringControllerTest.php`
- Create: `panel/tests/Feature/Portal/UsageControllerTest.php`

**Interfaces:**
- Produces:
  - Admin view `/panel/admin/monitoring`: Infrastructure health cards, worker fleet status, RabbitMQ queue depth, top tenants storage quota leaderboard.
  - Portal view `/panel/settings/usage`: Tenant storage progress bar, asset breakdown (originals, HLS, thumbnails), 30-day views and estimated egress.

- [ ] **Step 1: Write feature tests for Admin and Portal controllers**

1. Create `panel/tests/Feature/Admin/MonitoringControllerTest.php`:
   - Assert admin user can access `route('admin.monitoring.index')`.
   - Assert Inertia page `admin/monitoring/index` receives health metrics, queue depth, and tenant storage rankings.
   - Assert non-admin user receives 403 Forbidden.
2. Create `panel/tests/Feature/Portal/UsageControllerTest.php`:
   - Assert authenticated tenant member can access `route('portal.settings.usage')`.
   - Assert Inertia page `portal/settings/usage` receives tenant quota, storage breakdown, and 30-day usage trends.

- [ ] **Step 2: Run test to verify failure**

Run:
```bash
docker compose exec -T panel php artisan test tests/Feature/Admin/MonitoringControllerTest.php
docker compose exec -T panel php artisan test tests/Feature/Portal/UsageControllerTest.php
```
Expected: FAIL.

- [ ] **Step 3: Implement Controllers & Route Registration**

1. Create `panel/app/Http/Controllers/Admin/MonitoringController.php`:
   - Queries `HealthCheckServiceInterface::checkDiagnostics()`.
   - Queries top tenants ordered by total `VideoAsset` storage bytes with quota comparison.
   - Renders Inertia page `admin/monitoring/index`.
2. Create `panel/app/Http/Controllers/Portal/Settings/UsageController.php`:
   - Injects `TenantUsageServiceInterface`.
   - Retrieves active tenant's quota, storage by asset type (`ORIGINAL`, `HLS_VARIANT`, `THUMBNAIL`), and 30-day `TenantUsageRecord` collection.
   - Renders Inertia page `portal/settings/usage`.
3. Register routes:
   - In `panel/routes/admin.php`: `Route::get('monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');`
   - In `panel/routes/portal.php`: under settings, `Route::get('usage', [UsageController::class, 'index'])->name('settings.usage');`

- [ ] **Step 4: Create TypeScript types and React Views**

1. Create `panel/resources/js/types/monitoring.ts` and `panel/resources/js/types/usage.ts`.
2. Create `panel/resources/js/pages/admin/monitoring/index.tsx`:
   - Grid of infrastructure component status cards with color-coded latency pills (<50ms green, 50-200ms yellow, >200ms red).
   - Worker instances table (worker ID, hostname, age in seconds).
   - Tenant storage leaderboard with percentage progress bars.
3. Create `panel/resources/js/pages/portal/settings/usage.tsx`:
   - Clean storage usage bar (e.g. "4.2 GB of 10 GB used (42%)").
   - Storage breakdown cards (Original Videos, Transcoded HLS, Thumbnails).
   - Monthly view counts and streaming egress metrics.

- [ ] **Step 5: Run tests, Pint, and TypeScript checks**

Run:
```bash
docker compose exec -T panel php artisan test tests/Feature/Admin/MonitoringControllerTest.php
docker compose exec -T panel php artisan test tests/Feature/Portal/UsageControllerTest.php
docker compose exec -T panel ./vendor/bin/pint --test
docker compose exec -T panel npm run types
```
Expected: ALL PASS (0 errors).

- [ ] **Step 6: Commit**

```bash
git add panel/app/Http/Controllers/Admin/MonitoringController.php panel/app/Http/Controllers/Portal/Settings/UsageController.php panel/routes/ panel/resources/js/ panel/tests/Feature/
git commit -m "feat(ui): implement admin system monitoring dashboard and tenant usage views"
```

---

### Task 7: Disaster Recovery Automation & End-to-End Verification

**Files:**
- Create: `infrastructure/scripts/backup-postgres.sh`
- Create: `infrastructure/scripts/restore-postgres.sh`
- Create: `infrastructure/scripts/backup-minio.sh`
- Create: `docs/backup-disaster-recovery.md`
- Create: `panel/tests/Feature/Monitoring/EndToEndSystemReadinessTest.php`

**Interfaces:**
- Produces:
  - Validated database backup script producing timestamped `.sql.gz` dump.
  - Safe database restore script with schema rebuild.
  - MinIO client bucket synchronization script.
  - Disaster recovery runbook in `docs/backup-disaster-recovery.md`.
  - Comprehensive end-to-end integration test.

- [ ] **Step 1: Write Backup and Restore Bash Scripts**

1. Create `infrastructure/scripts/backup-postgres.sh`:
   - Uses `docker compose exec -T postgres pg_dump -U wiz_user -d wiz_video_platform | gzip > infrastructure/backups/postgres/wiz_postgres_$(date +%Y%m%d_%H%M%S).sql.gz`.
   - Validates exit status and non-empty archive.
2. Create `infrastructure/scripts/restore-postgres.sh`:
   - Takes dump path `$1`.
   - Confirms database rebuild, drops schema public, imports with `gunzip -c "$1" | docker compose exec -T postgres psql -U wiz_user -d wiz_video_platform`.
3. Create `infrastructure/scripts/backup-minio.sh`:
   - Uses `docker compose exec -T panel` or MinIO client to run `mc mirror --overwrite` for `videos-original`, `videos-processed`, `thumbnails`.
4. Make scripts executable (`chmod +x infrastructure/scripts/*.sh`).

- [ ] **Step 2: Write Disaster Recovery Runbook**

Create `docs/backup-disaster-recovery.md`:
- Detailed instructions for cold-start restore on a new machine.
- Environment restoration steps (`.env`, secrets, encryption keys).
- Database restore commands and verification queries.
- MinIO object storage restore commands and bucket permissions.

- [ ] **Step 3: Write and Run End-to-End System Readiness Test**

Create `panel/tests/Feature/Monitoring/EndToEndSystemReadinessTest.php`:
- Verify all health endpoints (`/health` and `/api/v1/admin/health`).
- Verify tenant storage quota calculation with live `VideoAsset` records.
- Verify playback auth subrequest records view in Redis and increments egress.
- Verify running `usage:aggregate-bandwidth` persists usage into `tenant_usage_records`.

- [ ] **Step 4: Execute Full Test Suite Across Worker and Panel**

Run:
```bash
docker run --rm -v "D:\Playground\wiz-video-platform\worker:/app" -w /app golang:1.23-alpine go test -v ./...
docker compose exec -T panel php artisan test
docker compose exec -T panel ./vendor/bin/pint --test
docker compose exec -T panel npm run types
```
Expected: ALL GREEN (100% test pass rate across both Go and Laravel, 0 Pint violations, 0 TypeScript errors).

- [ ] **Step 5: Commit**

```bash
git add infrastructure/scripts/ docs/backup-disaster-recovery.md panel/tests/Feature/Monitoring/EndToEndSystemReadinessTest.php
git commit -m "feat(ops): add disaster recovery scripts, runbook, and end-to-end readiness verification"
```
