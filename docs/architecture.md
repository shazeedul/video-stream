# Architecture Specification: Video Streaming Platform

Finalized MVP architecture for multi-tenant video hosting SaaS.

## 1. Final technology and responsibility map

| Layer                 | Technology         | Responsibility                                         |
| --------------------- | ------------------ | ------------------------------------------------------ |
| Customer and admin UI | Inertia.js + React | Dashboard, video library, settings                     |
| Control plane         | Laravel            | Tenants, users, API, authorization, billing foundation |
| Database              | PostgreSQL         | Tenant and video metadata                              |
| Cache                 | Redis              | Cache, locks, sessions, Laravel queues                 |
| Object storage        | MinIO              | Original videos and processed media                    |
| Message broker        | RabbitMQ           | Media-processing jobs                                  |
| Media worker          | Go                 | Processing orchestration and job lifecycle             |
| Media engine          | FFmpeg / FFprobe   | Probing, transcoding, HLS packaging                    |
| Reverse proxy         | Nginx              | TLS, routing, playback authorization                   |
| Player                | Video.js           | Hosted player and embed integration                    |
| Realtime updates      | Laravel Reverb     | Upload and processing progress                         |

Important design boundary: Laravel owns business rules and video state. Go owns media processing. RabbitMQ connects them through versioned messages. MinIO stores the media; PostgreSQL stores its metadata.

## 2. What the existing repository provides

- Laravel 13, PHP 8.4
- Inertia v2, React 19, Tailwind v4
- Admin routes: `routes/admin.php`
- Portal routes: `routes/portal.php`
- Role and permissions source of truth: `app/Support/Authorization/RolePermissions.php`
- Wayfinder for typed frontend routes and actions
- Sanctum API authentication
- Reverb + Echo for realtime events
- Horizon for Redis queues

Target separation:
1. Global admin and user roles are platform-level. Tenant membership and tenant-level authorization are separate.
2. PostgreSQL is the target database engine for all persistence.

## 3. Monorepo structure

```
wiz-video-platform/
│
├── panel/                             # Laravel + Inertia + React application
│   ├── app/
│   │   ├── Domain/
│   │   │   ├── Tenants/               # Tenant models, policies, services
│   │   │   ├── Videos/
│   │   │   ├── Uploads/
│   │   │   ├── Processing/
│   │   │   ├── Playback/
│   │   │   └── Usage/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Admin/
│   │   │   │   └── Portal/
│   │   │   ├── Middleware/
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Models/
│   │   ├── Policies/
│   │   ├── Services/
│   │   └── Support/
│   ├── database/
│   │   ├── migrations/
│   │   ├── factories/
│   │   └── seeders/
│   ├── resources/js/
│   │   ├── pages/
│   │   │   ├── admin/
│   │   │   └── portal/
│   │   └── components/
│   └── routes/
│       ├── admin.php
│       ├── portal.php
│       └── api.php
│
├── worker/                            # Go media processor service
│   ├── cmd/worker/
│   ├── internal/
│   │   ├── config/
│   │   ├── consumer/
│   │   ├── pipeline/
│   │   ├── probe/
│   │   ├── transcoder/
│   │   ├── hls/
│   │   ├── storage/
│   │   ├── callback/
│   │   └── observability/
│   ├── Dockerfile
│   └── go.mod
│
├── infrastructure/
│   ├── docker/
│   ├── nginx/
│   ├── minio/
│   └── rabbitmq/
│
├── docs/
│   ├── architecture.md
│   └── superpowers/plans/
│
└── docker-compose.yml
```

## 4. Multi-Tenant Authorization Model

Platform-wide authorization is separated from tenant membership:
- **Platform Administrator**: Uses Spatie roles and permissions via `RolePermissions.php`. Manages all tenants and system infrastructure.
- **Tenant Owner**: Owns the customer organization; manages tenant profile, members, and billing.
- **Tenant Admin**: Manages team members and videos within the organization.
- **Tenant Member**: Creates and edits videos and uploads within the organization.
- **Tenant Viewer**: Views organization media without edit or upload permissions.

Every tenant-owned resource operation verifies membership and role against the active tenant.

## 5. Phases of Delivery

- **Phase 1: Foundation and Multi-Tenancy** (Docker Compose, PostgreSQL, Tenants, TenantUser membership, Authorization/Policies, Admin & Portal screens)
- **Phase 2: Video Library and Uploads** (MinIO S3 adapter, multipart presigned uploads, Video CRUD, library pages)
- **Phase 3: RabbitMQ and Go Worker** (Outbox publisher, Go worker skeleton, job consumption, FFprobe)
- **Phase 4: Transcoding and HLS** (FFmpeg renditions ladder, HLS playlists, thumbnails, MinIO asset sync, Laravel callbacks)
- **Phase 5: Playback and Embedding** (Video.js React player, signed tokens, Nginx playback auth, embed iframe)
- **Phase 6: Usage, Monitoring, and Production Readiness** (Usage tracking, admin monitoring, healthchecks, backup/restore)
