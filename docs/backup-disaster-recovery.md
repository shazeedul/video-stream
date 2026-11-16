# Wiz Video Platform - Backup & Disaster Recovery Runbook

This runbook outlines the operational procedures for backing up and performing a cold-start disaster recovery restoration of the Wiz Video Platform on a fresh host.

---

## 1. System Overview & Objectives

* **Target Recovery Time Objective (RTO):** < 30 minutes
* **Target Recovery Point Objective (RPO):** < 1 hour (subject to backup frequency)
* **Persistent State Components:**
  * **PostgreSQL 16:** Relational data (tenants, users, roles, videos, video assets, usage records).
  * **MinIO Object Storage:** Media files across three distinct buckets:
    * `videos-original`: Raw uploads directly from users/tus multipart uploads.
    * `videos-processed`: Transcoded HLS manifests (`.m3u8`) and video transport stream segments (`.ts`).
    * `thumbnails`: Generated video preview poster images (`.jpg`).
  * **Environment & Encryption Keys:** `.env` secrets (`APP_KEY`, `INTERNAL_API_SECRET`, MinIO root credentials).

---

## 2. Backup Procedures & Automation

All backup scripts are located in `infrastructure/scripts/` and operate with strict error checking (`set -euo pipefail`).

### 2.1 PostgreSQL Database Backup

The database backup script runs `pg_dump` inside the PostgreSQL container, compresses the output via `gzip`, and saves it to a timestamped file:

```bash
# Default output directory: infrastructure/backups/postgres/
bash infrastructure/scripts/backup-postgres.sh
```

**Customizing Parameters via Environment Variables:**
* `BACKUP_DIR`: Directory where dumps are stored (default: `infrastructure/backups/postgres`).
* `POSTGRES_USER`: Database user (default: `wiz_user`).
* `POSTGRES_DB`: Database name (default: `wiz_video`).
* `COMPOSE_SERVICE`: Docker compose service name (default: `postgres`).

The script automatically validates that the generated `.sql.gz` file exists and is greater than 0 bytes.

### 2.2 MinIO Object Storage Backup

The MinIO backup script synchronizes all three platform buckets (`videos-original`, `videos-processed`, and `thumbnails`) to a local backup mirror:

```bash
# Default output directory: infrastructure/backups/minio/
bash infrastructure/scripts/backup-minio.sh
```

**Customizing Parameters via Environment Variables:**
* `BACKUP_DIR`: Directory where mirrored buckets are placed (default: `infrastructure/backups/minio`).
* `MINIO_ENDPOINT`: Internal endpoint for docker network (default: `http://minio:9000`).
* `MINIO_HOST_ENDPOINT`: Host endpoint when using local `mc` CLI (default: `http://localhost:9000`).
* `MINIO_ROOT_USER`: MinIO admin user (default: `minioadmin`).
* `MINIO_ROOT_PASSWORD`: MinIO admin password (default: `minioadmin`).

### 2.3 Recommended Crontab Configuration

Run database backups hourly and MinIO synchronization every 6 hours:

```cron
# Every hour at minute 0: PostgreSQL database backup
0 * * * * cd /opt/wiz-video-platform && bash infrastructure/scripts/backup-postgres.sh >> /var/log/wiz-backup-db.log 2>&1

# Every 6 hours: MinIO object storage sync
0 */6 * * * cd /opt/wiz-video-platform && bash infrastructure/scripts/backup-minio.sh >> /var/log/wiz-backup-minio.log 2>&1
```

---

## 3. Cold-Start Disaster Recovery Procedure

Follow these steps sequentially to restore the platform on a fresh server.

### Step 1: Host Prerequisites & Repository Setup

Ensure Docker Engine (24+), Docker Compose (v2+), and Git are installed.

```bash
# Clone the repository
git clone <repository_url> /opt/wiz-video-platform
cd /opt/wiz-video-platform
```

### Step 2: Environment & Secrets Restoration

Restore the `.env` file from secure off-site secret storage (e.g., HashiCorp Vault, AWS Secrets Manager, or 1Password).

> [!IMPORTANT]
> The `APP_KEY` must match the original instance to decrypt encrypted database attributes and sessions.

Verify critical environment variables:
```bash
cp .env.example .env

# Verify or populate the following:
APP_KEY="base64:..." # Must match previous environment!
INTERNAL_API_SECRET="..."
DB_DATABASE=wiz_video
DB_USERNAME=wiz_user
DB_PASSWORD=secret
MINIO_ROOT_USER=minioadmin
MINIO_ROOT_PASSWORD=minioadmin
```

### Step 3: Boot Infrastructure Services

Start the core persistent and messaging services first:

```bash
docker compose up -d postgres redis rabbitmq minio
```

Wait 10-15 seconds for PostgreSQL and MinIO to finish initial startup.

### Step 4: Restore PostgreSQL Database

Locate your latest database dump (e.g. `wiz_postgres_YYYYMMDD_HHMMSS.sql.gz`) and execute the automated restore script:

```bash
bash infrastructure/scripts/restore-postgres.sh /path/to/wiz_postgres_20261116_194010.sql.gz --force
```

The script will:
1. Verify the dump file is non-empty.
2. Drop and recreate schema `public`.
3. Gunzip and stream the SQL dump into `wiz_postgres`.

Verify that the tables exist and records are populated:
```bash
docker compose exec -T postgres psql -U wiz_user -d wiz_video -c "SELECT count(*) FROM tenants;"
docker compose exec -T postgres psql -U wiz_user -d wiz_video -c "SELECT count(*) FROM videos;"
```

Run pending migrations (if any):
```bash
docker compose run --rm panel php artisan migrate --force
```

### Step 5: Restore MinIO Object Storage

1. Ensure the buckets exist in MinIO:
```bash
docker compose exec -T minio mc mb local/videos-original || true
docker compose exec -T minio mc mb local/videos-processed || true
docker compose exec -T minio mc mb local/thumbnails || true
```

2. Restore files from the backup directory:
```bash
# Using MinIO container mc mirror
NETWORK_NAME=$(docker inspect wiz_minio --format '{{range $k, $v := .NetworkSettings.Networks}}{{$k}}{{end}}')
BACKUP_SOURCE="/path/to/infrastructure/backups/minio"

docker run --rm \
    --network "${NETWORK_NAME}" \
    -v "${BACKUP_SOURCE}:/backup" \
    minio/minio:latest \
    sh -c "mc alias set minio_target http://minio:9000 minioadmin minioadmin && \
           mc mirror --overwrite /backup/videos-original minio_target/videos-original && \
           mc mirror --overwrite /backup/videos-processed minio_target/videos-processed && \
           mc mirror --overwrite /backup/thumbnails minio_target/thumbnails"
```

3. Configure bucket read policies for processed HLS videos:
```bash
docker compose exec -T panel php artisan tinker --execute="
\$client = Storage::disk('minio-processed')->getClient();
\$bucket = config('filesystems.disks.minio-processed.bucket', 'videos-processed');
\$policy = json_encode([
    'Version' => '2012-10-17',
    'Statement' => [[
        'Effect' => 'Allow',
        'Principal' => ['AWS' => ['*']],
        'Action' => ['s3:GetBucketLocation', 's3:ListBucket', 's3:GetObject'],
        'Resource' => [\"arn:aws:s3:::\$bucket\", \"arn:aws:s3:::\$bucket/*\"]
    ]]
]);
\$client->putBucketPolicy(['Bucket' => \$bucket, 'Policy' => \$policy]);
"
```

### Step 6: Start Application & Worker Services

Once persistent state is restored, launch the web panel, media workers, and Nginx reverse proxy:

```bash
docker compose up -d panel worker nginx
```

---

## 4. Post-Restoration Verification & Health Checks

Execute the platform diagnostic endpoints to verify overall operational readiness:

### 4.1 Liveness Probe

```bash
curl -i http://localhost:8080/health
```
**Expected Response:** `HTTP/1.1 200 OK`
```json
{
  "status": "ok",
  "database": "ok",
  "redis": "ok"
}
```

### 4.2 Comprehensive Admin Diagnostics

Generate an admin Sanctum token or query with admin authentication:
```bash
curl -i -H "Authorization: Bearer <ADMIN_TOKEN>" http://localhost:8080/api/v1/admin/health
```
**Expected Response:** `HTTP/1.1 200 OK`
```json
{
  "status": "ok",
  "timestamp": "2026-11-16T19:40:00Z",
  "components": {
    "database": { "status": "ok", "latency_ms": 1.4 },
    "redis": { "status": "ok", "latency_ms": 0.8 },
    "minio": { "status": "ok", "buckets": ["videos-original", "videos-processed", "thumbnails"], "latency_ms": 5.2 },
    "rabbitmq": { "status": "ok", "queue_depth": 0, "consumers": 1, "latency_ms": 2.1 },
    "workers": { "status": "ok", "active_count": 1, "instances": [...] }
  }
}
```

### 4.3 Database Integrity Queries

Run sanity counts to ensure data fidelity:
```bash
docker compose exec -T postgres psql -U wiz_user -d wiz_video -c "
SELECT
    (SELECT COUNT(*) FROM tenants) AS tenants_count,
    (SELECT COUNT(*) FROM users) AS users_count,
    (SELECT COUNT(*) FROM videos) AS videos_count,
    (SELECT COUNT(*) FROM video_assets) AS assets_count,
    (SELECT COUNT(*) FROM tenant_usage_records) AS usage_records_count;
"
```

---

## 5. Troubleshooting & Edge Cases

### Issue: PostgreSQL Schema Rebuild Fails Due to Active Connections
**Symptom:** `ERROR: database "wiz_video" is being accessed by other users`
**Remedy:** Terminate all active database connections before running restore:
```bash
docker compose exec -T postgres psql -U wiz_user -d wiz_video -c "
SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = 'wiz_video' AND pid <> pg_backend_pid();
"
```

### Issue: MinIO Buckets Return 403 Forbidden on Playback
**Symptom:** Nginx returns `403 Forbidden` or `502 Bad Gateway` when requesting `.m3u8` or `.ts` chunks.
**Remedy:**
1. Verify `videos-processed` bucket exists: `docker compose exec -T minio mc ls local`.
2. Check that the MinIO read policy is applied as described in Step 5.
3. Check Nginx logs: `docker compose logs nginx --tail 50`.

### Issue: Worker Not Registering Heartbeat
**Symptom:** `/api/v1/admin/health` reports `workers.status: "error"`, `active_count: 0`.
**Remedy:**
1. Verify RabbitMQ is running and accepts connections on port 5672.
2. Check worker logs: `docker compose logs worker --tail 50`.
3. Verify that `INTERNAL_API_SECRET` in `docker-compose.yml` matches between `panel` and `worker`.
