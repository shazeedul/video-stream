# Phase 6 Design Document: Usage Tracking, Monitoring, and Production Readiness

## 1. Intent and Why

As a multi-tenant video platform hosting user-generated media and streaming HLS video, Wiz Video Platform requires:
1. **Resource Governance & Quotas**: Preventing tenant runaway storage and unconstrained bandwidth consumption through configurable quotas and proactive enforcement.
2. **System Observability & Health**: Providing immediate visibility into service degradation (database, cache, object storage, message broker, media worker) to maintain high availability without triggering cascading web outages.
3. **Operational Resilience**: Establishing automated database backup and disaster recovery procedures for production deployments.

---

## 2. Goals, Non-Goals, and Anti-Goals

### Goals
- **Storage Accounting**: Maintain accurate, real-time aggregate storage usage per tenant based on original video uploads (`videos-original`), transcoded HLS variants (`videos-processed`), poster thumbnails (`thumbnails`), and in-flight upload sessions.
- **Quota Enforcement**: Reject new upload initiations (`POST /panel/videos/{video}/upload/initiate`) when a tenant's current storage plus pending uploads exceeds their assigned quota.
- **Low-Overhead Egress & View Metering**: Track playback bandwidth and view counts using Redis atomic counters populated during Nginx internal playback authorization subrequests, aggregated periodically into PostgreSQL.
- **Fault-Tolerant Health Checks**: Provide `/health` (shallow liveness probe for web/DB/Redis) and `/api/v1/admin/health` (deep diagnostics with component latencies for PostgreSQL, Redis, MinIO buckets, RabbitMQ queue depth, and multi-worker heartbeats).
- **Admin Monitoring Dashboard**: Deliver an administrative interface displaying system health badges, queue depth, active worker registry, and per-tenant resource utilization rankings.
- **Backup & Recovery Automation**: Provide automated scripts for PostgreSQL database snapshots and restoration, alongside MinIO bucket synchronization guidance.

### Non-Goals
- **Direct Credit Card / Billing Gateway Integration**: Stripe/Paddle billing checkout is deferred; Phase 6 provides the usage records and quota primitives for future billing hooks.
- **Packet-Level Nginx Raw TCP Logging**: Does not parse gigabytes of raw Nginx access logs or deploy sidecar log aggregators; egress is estimated via authorized playback requests.

### Anti-Goals
- **Degrading Video Streaming Performance**: Under no circumstances should playback requests perform blocking SQL transactions. Egress tracking must remain in-memory (Redis) with asynchronous persistence.
- **Cascading Outages from Background Workers**: Failure or stoppage of background transcoding workers or RabbitMQ must never cause the public `/health` probe to return 503 and crash web containers.
- **Cross-Tenant Data Exposure**: Usage metrics, storage breakdowns, and quotas must remain strictly tenant-isolated in customer-facing views.

---

## 3. Constraints

- **Language & Runtime Standards**:
  - PHP: PHP 8.4, Laravel 11/13, strict typing (`declare(strict_types=1);`), `final class` for all new domain services and controllers, 100% Laravel Pint compliance.
  - Go: Go 1.23, structured logging (`slog`), non-blocking heartbeat publisher in worker loop.
  - TypeScript / React: React 19, Inertia.js v2, strict typing with zero compiler warnings (`npm run types`).
- **Performance Budget**:
  - `/health` response time < 25ms.
  - Playback auth tracking overhead < 1.5ms (atomic Redis operations).
- **Bucket Names**: Strictly align with `config/filesystems.php`:
  - `videos-original` (raw uploads)
  - `videos-processed` (HLS playlists and `.ts` media segments)
  - `thumbnails` (poster JPEG frames)

---

## 4. Approaches Considered and Selection

### Alternative 1: Storage Quotas Only (No Egress Tracking)
- Track storage byte totals from `VideoAsset` records and enforce upload limits, omitting bandwidth tracking entirely.
- *Trade-off*: Simplest implementation, but leaves platform operators blind to heavy streaming bandwidth usage.

### Alternative 2: Nginx Access Log Ingestion / Syslog Parser
- Configure Nginx to forward access logs via Syslog/Fluentd to a parsing container that calculates exact byte counts per TCP response.
- *Trade-off*: 100% exact byte accuracy, but introduces container dependencies, high disk I/O, and operational complexity.

### Alternative 3 (Selected): Token & Auth-Based Session Tracking via Redis
- In `PlaybackAuthController`, upon validating a playback request:
  - If the request targets `master.m3u8`: record a **video view** if not already recorded for that viewer/token within a 30-minute deduplication window (Redis key: `view:dedup:{video_id}:{token_hash}`).
  - If the request targets a `.ts` media segment: increment the tenant's daily egress counter `tenant:{id}:egress:{YYYY-MM-DD}` by the segment size (calculated as ~1.5 MB default or proportional to rendition bitrate * segment duration).
- An hourly Artisan console command (`usage:aggregate-bandwidth`) synchronizes Redis deltas into the `tenant_usage_records` PostgreSQL table.
- Storage is computed from `VideoAsset::sum('size_bytes')` plus pending `UploadSession::where('status', 'uploading')->sum('file_size_bytes')`.

---

## 5. Architectural Specification & Component Design

```
+-----------------------------------------------------------------------------------+
|                                 ARCHITECTURE                                      |
+-----------------------------------------------------------------------------------+
|                                                                                   |
|  [Client Player]                                                                  |
|         │                                                                         |
|         ▼ (GET /playback/{video_id}/{asset}?... )                                 |
|    [Nginx Edge] ──(auth_request)──▶ [Laravel: PlaybackAuthController]             |
|         │                                    │                                    |
|         │ (200 OK with X-Tenant-Id)          ├── master.m3u8? ──▶ Dedupe & View +1|
|         ▼                                    │                                    |
|    [MinIO Storage]                           └── .ts segment? ──▶ Egress bytes +X |
|    - videos-processed                                │                            |
|                                                      ▼                            |
|                                            [Redis Cache / Counters]               |
|                                                      │                            |
|                                            [Artisan: usage:aggregate]             |
|                                                      │ (Hourly Flush)             |
|                                                      ▼                            |
|                                        [PostgreSQL: tenant_usage_records]         |
|                                                      ▲                            |
|                                                      │                            |
|  [Admin UI: Monitoring] ◀────────────────────────────┴─── [HealthCheckService]    |
|                                                                  │ (Probes)       |
|                                                                  ├──▶ PostgreSQL  |
|                                                                  ├──▶ Redis       |
|                                                                  ├──▶ MinIO       |
|                                                                  ├──▶ RabbitMQ    |
|                                                                  └──▶ Go Workers  |
+-----------------------------------------------------------------------------------+
```

### 5.1 Domain Layer: Usage & Quotas (`App\Domain\Usage`)

#### Models & Migrations
1. **`TenantUsageRecord`**:
   - Table: `tenant_usage_records`
   - Columns:
     - `id` (UUID, primary key)
     - `tenant_id` (UUID, foreign key to `tenants.id`, indexed)
     - `date` (DATE, indexed with `tenant_id` unique composite: `[tenant_id, date]`)
     - `storage_bytes` (BIGINT, unsigned, default 0)
     - `egress_bytes` (BIGINT, unsigned, default 0)
     - `view_count` (BIGINT, unsigned, default 0)
     - `video_count` (INT, unsigned, default 0)
     - `created_at`, `updated_at`

2. **Tenant Quotas Configuration**:
   - Stored in `tenants.settings`: `storage_quota_bytes` (nullable BIGINT; default 10 GB when null).
   - Helper methods on `Tenant`:
     - `getStorageQuotaBytes(): int`: returns configured quota or default 10 GB.
     - `getStorageUsedBytes(): int`: sums all completed `VideoAsset` sizes + active `UploadSession` file sizes.
     - `hasExceededStorageQuota(int $additionalBytes = 0): bool`.

3. **Quota Policy & Transcoding Expansion**:
   - **Upload Gate**: At `VideoUploadController::initiate`, reject with `422 Unprocessable Entity` ("Tenant storage quota exceeded. Please upgrade your plan or delete existing media.") if `current_storage + file_size > quota`.
   - **Transcoding Output**: Transcoding is treated with a **soft limit**. Transcoded HLS renditions and thumbnails are always registered to completion. Once registered, they count towards total storage, which will block subsequent upload initiations until storage is freed.

4. **Services**:
   - **`TenantUsageService` (`TenantUsageServiceInterface`)**:
     - `recordPlaybackView(string $tenantId, string $videoId, string $clientToken): void` (dedupes with 30-min Redis TTL).
     - `recordPlaybackEgress(string $tenantId, int $bytes): void` (Redis `INCRBYFLOAT`).
     - `getTenantCurrentStorage(Tenant $tenant): int`
     - `aggregateHourlyUsage(): int` (persists Redis counters to `tenant_usage_records`).
     - `recalculateTenantStorage(Tenant $tenant): int`

### 5.2 System Health Checks (`App\Domain\Monitoring`)

#### Health Check Architecture
- **Shallow Liveness Probe (`GET /health`)**:
  - Purpose: Container and load-balancer liveness check.
  - Probes:
    - **PostgreSQL**: `DB::connection()->getPdo()` (fatal if down -> HTTP 503).
    - **Redis**: `Redis::ping()` (fatal if down -> HTTP 503).
  - Background services (RabbitMQ, MinIO, Go Workers) do **not** trigger a 503 on `/health` to prevent web application cascading downtime. Instead, `/health` returns HTTP 200 with JSON `{"status": "degraded"}` if background services are down.
  
- **Deep Administrative Health Probe (`GET /api/v1/admin/health`)**:
  - Requires `admin.panel` authentication.
  - Probes:
    1. **PostgreSQL**: `SELECT 1` with query latency ms.
    2. **Redis**: `PING` with latency ms.
    3. **MinIO Storage**: verifies bucket existence for `videos-original`, `videos-processed`, and `thumbnails`.
    4. **RabbitMQ**: AMQP connection check and `media.jobs` queue message/consumer count.
    5. **Go Media Workers**: inspects worker heartbeat registry (count of active workers, last heartbeat timestamps).
  - Response Schema:
    ```json
    {
      "status": "healthy",
      "timestamp": "2026-11-16T15:30:00Z",
      "components": {
        "database": { "status": "ok", "latency_ms": 1.2 },
        "redis": { "status": "ok", "latency_ms": 0.5 },
        "minio": { "status": "ok", "buckets": ["videos-original", "videos-processed", "thumbnails"], "latency_ms": 4.1 },
        "rabbitmq": { "status": "ok", "queue_depth": 0, "consumers": 1, "latency_ms": 2.3 },
        "workers": { "status": "ok", "active_count": 1, "instances": [{ "id": "worker-1", "age_seconds": 5 }] }
      }
    }
    ```

### 5.3 Go Worker Multi-Instance Heartbeat Registry
- In `worker/internal/observability/heartbeat.go`:
  - Worker generates a unique instance ID on startup (e.g., `worker-{hostname}-{uuid[:8]}`).
  - Runs background goroutine ticking every 15 seconds.
  - Writes to Redis:
    - Key `worker:heartbeat:{worker_id}` with TTL of 45 seconds containing JSON `{"id": "...", "hostname": "...", "timestamp": unix_time}`.
    - Adds `worker_id` to Redis Set `workers:active`.
  - On SIGINT / SIGTERM clean shutdown:
    - Removes `worker_id` from Set `workers:active` and deletes key `worker:heartbeat:{worker_id}`.
- In Laravel `HealthCheckService`:
  - Reads active worker IDs from `workers:active`. Prunes any whose TTL has expired. If count > 0, worker status is `OK`. If count == 0, status is `DOWN`.

### 5.4 Admin System Monitoring UI
- **Route**: `GET /panel/admin/monitoring` (`admin.monitoring.index`).
- **Controller**: `App\Http\Controllers\Admin\MonitoringController`.
- **View**: `panel/resources/js/pages/admin/monitoring/index.tsx`.
- **Widgets**:
  1. **Infrastructure Health Badges**: Real-time status cards (Database, Redis, MinIO, RabbitMQ, Media Workers) with latency and consumer count.
  2. **Active Worker Fleet**: List of running worker daemons, hostnames, and heartbeat ages.
  3. **Queue Health**: RabbitMQ `media.jobs` pending count and processing throughput.
  4. **Tenant Storage Leaderboard**: Top 10 tenants by storage usage with quota visualization bars.
  5. **Platform Metrics Overview**: Total video assets stored, total storage (GB/TB), 30-day aggregate egress (GB).

### 5.5 Customer Portal Usage UI
- **Route**: `GET /panel/settings/usage` (`portal.settings.usage`).
- **Controller**: `App\Http\Controllers\Portal\Settings\UsageController`.
- **View**: `panel/resources/js/pages/portal/settings/usage.tsx`.
- **Display**:
  - Storage consumption progress bar against quota (e.g., "4.2 GB / 10 GB (42%)").
  - Breakdown by asset category (Original Media vs. Transcoded HLS Variants vs. Poster Thumbnails).
  - 30-day view count and estimated streaming bandwidth chart/card.

### 5.6 Backup & Disaster Recovery Scripts
- **Directory**: `infrastructure/scripts/`
- **`backup-postgres.sh`**:
  - Executes `pg_dump` via `docker compose exec -T postgres` with gzip compression.
  - Emits timestamped archive: `infrastructure/backups/postgres/wiz_postgres_YYYYMMDD_HHMMSS.sql.gz`.
  - Verifies non-zero exit code and valid archive size.
- **`restore-postgres.sh`**:
  - Accepts backup path parameter: `./restore-postgres.sh <backup_file.sql.gz>`.
  - Drops existing public schema, recreates, and imports data inside `wiz_postgres`.
  - Includes confirmation guard (`--force` or interactive confirmation).
- **`backup-minio.sh` & `docs/backup-disaster-recovery.md`**:
  - Automated MinIO Client (`mc mirror`) script targeting backup directory or external S3 target.
  - Complete disaster recovery runbook detailing step-by-step restore of Postgres + MinIO from cold storage.

---

## 6. What is Left to the Builder

- Exact CSS/Tailwind layout and colors for latency badges (<50ms green, 50-200ms yellow, >200ms red).
- Cron frequency for `usage:aggregate-bandwidth` (scheduled hourly in `routes/console.php`).
- Exact default segment size estimate (1.5 MB per `.ts` chunk auth request if rendition bitrate is not extracted from URL).
