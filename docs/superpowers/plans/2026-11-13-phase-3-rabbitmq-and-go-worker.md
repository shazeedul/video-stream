# Phase 3: RabbitMQ and Go Worker Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Establish an asynchronous, resilient media processing pipeline by integrating a PostgreSQL transactional outbox and RabbitMQ publisher in Laravel, a standalone containerized Go worker that consumes jobs and inspects media with FFprobe, and a secure internal callback API to persist technical video metadata.

**Architecture:** When a video upload completes in Laravel, an outbox message is atomically recorded in PostgreSQL and the video transitions to `QUEUED`. An outbox publisher pushes events to RabbitMQ (`media.jobs`). A dedicated Go worker daemon consumes the job, reports `PROBING` state via an authenticated internal HTTP callback, downloads/streams the source file from MinIO, executes `ffprobe` to extract streams/format, and posts the probed duration and metadata back to Laravel before acknowledging the message.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL 16 (port 5433 host / 5432 container), `php-amqplib/php-amqplib`, RabbitMQ 3.13 (AMQP 0-9-1), Go 1.23, `github.com/rabbitmq/amqp091-go`, `github.com/minio/minio-go/v7`, FFmpeg/FFprobe (in Alpine Docker), Docker Compose.

**Spec:** `docs/architecture.md` and Brainstorming Design Document approved on 2026-11-13.

## Global Constraints

- **PHP Standards:** `declare(strict_types=1);` and `final class` on all new PHP classes, enforced by Laravel Pint.
- **Go Standards:** Idiomatic Go 1.23+, standard directory layout (`cmd/`, `internal/`), structured logging with `log/slog`, strict error handling with context cancellation.
- **Database:** PostgreSQL 16 in Docker (`wiz_postgres`, host port 5433:5432). All migrations and tests execute against PostgreSQL.
- **Storage:** MinIO in Docker (`wiz_minio`, host port 9000:9000).
- **Message Broker:** RabbitMQ in Docker (`wiz_rabbitmq`, host port 5672:5672, management 15672:15672).
- **Security:** Internal callback API must be protected by a constant-time shared secret (`X-Internal-Secret`) and reject any unauthorized or malformed requests.
- **Idempotency:** Outbox messages and worker callbacks must be idempotent; repeated processing of a job must not duplicate assets or corrupt video states.

## Review Focus

1. **Transient RabbitMQ Disconnection During Outbox Publishing:** When RabbitMQ is temporarily down, the outbox publisher must fail gracefully, increment attempts, record error message, and preserve messages in `PENDING` status for retry without losing records.
2. **Corrupt or Unreadable Video Upload (FFprobe Failure):** When FFprobe encounters a truncated, corrupted, or non-media file, the Go worker must catch the error, report `status: failed` with a sanitized diagnostic message via callback to Laravel, and dead-letter (DLQ) or discard the message rather than crashing the worker loop.
3. **Internal Callback Network or 5xx Failures:** When the Laravel panel callback is temporarily unreachable (e.g. during deployment or container restart), the Go worker must retry callback dispatch with backoff before giving up and nacking the message.
4. **Poison/Malformed RabbitMQ Messages:** Messages with invalid JSON schema or missing mandatory fields (`job_id`, `video_id`, `source_key`) must be rejected without requeue (routing directly to `media.jobs.dlq`) to prevent endless worker crash loops.
5. **Video Deletion or Abort Race Condition:** If a video was deleted or aborted while a job was in flight, the Laravel callback endpoint must return HTTP 404/422 without triggering 500 server errors, and the worker must safely acknowledge and discard the job.

---

### Task 1: Transactional Outbox Schema & Domain Model in Laravel

**Files:**
- Create: `panel/database/migrations/2026_11_13_000001_create_media_outbox_messages_table.php`
- Create: `panel/app/Domain/Processing/Enums/OutboxStatus.php`
- Create: `panel/app/Domain/Processing/Models/MediaOutboxMessage.php`
- Test: `panel/tests/Feature/Processing/MediaOutboxModelTest.php`

**Interfaces:**
- Consumes: PostgreSQL connection (`wiz_postgres`).
- Produces: `MediaOutboxMessage` Eloquent model with scopes `pending()`, `published()`, `failed()` and helper methods `markPublished()`, `markFailed(string $error)`.

- [ ] **Step 1: Write failing test for MediaOutboxMessage model and scopes**

Create `panel/tests/Feature/Processing/MediaOutboxModelTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Processing;

use App\Domain\Processing\Enums\OutboxStatus;
use App\Domain\Processing\Models\MediaOutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MediaOutboxModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_outbox_message_with_pending_status_by_default(): void
    {
        $message = MediaOutboxMessage::create([
            'event_type' => 'video.uploaded',
            'aggregate_type' => 'video',
            'aggregate_id' => '019324c3-e836-7db0-bd1b-c40c83a5316e',
            'payload' => [
                'video_id' => '019324c3-e836-7db0-bd1b-c40c83a5316e',
                'source_bucket' => 'videos-original',
            ],
        ]);

        $this->assertDatabaseHas('media_outbox_messages', [
            'id' => $message->id,
            'event_type' => 'video.uploaded',
            'status' => OutboxStatus::PENDING->value,
            'attempts' => 0,
        ]);
        $this->assertEquals(OutboxStatus::PENDING, $message->status);
    }

    public function test_it_scopes_pending_messages(): void
    {
        $pending = MediaOutboxMessage::create([
            'event_type' => 'video.uploaded',
            'aggregate_type' => 'video',
            'aggregate_id' => '019324c3-e836-7db0-bd1b-c40c83a5316e',
            'payload' => ['foo' => 'bar'],
            'status' => OutboxStatus::PENDING,
        ]);

        $published = MediaOutboxMessage::create([
            'event_type' => 'video.uploaded',
            'aggregate_type' => 'video',
            'aggregate_id' => '019324c3-e836-7db0-bd1b-c40c83a5316f',
            'payload' => ['foo' => 'bar'],
            'status' => OutboxStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        $results = MediaOutboxMessage::pending()->get();

        $this->assertTrue($results->contains($pending));
        $this->assertFalse($results->contains($published));
    }

    public function test_mark_published_and_mark_failed_state_transitions(): void
    {
        $message = MediaOutboxMessage::create([
            'event_type' => 'video.uploaded',
            'aggregate_type' => 'video',
            'aggregate_id' => '019324c3-e836-7db0-bd1b-c40c83a5316e',
            'payload' => ['foo' => 'bar'],
        ]);

        $message->markFailed('Connection timed out');
        $this->assertEquals(OutboxStatus::FAILED, $message->fresh()->status);
        $this->assertEquals(1, $message->fresh()->attempts);
        $this->assertEquals('Connection timed out', $message->fresh()->error_message);

        $message->markPublished();
        $this->assertEquals(OutboxStatus::PUBLISHED, $message->fresh()->status);
        $this->assertNotNull($message->fresh()->published_at);
        $this->assertNull($message->fresh()->error_message);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T panel php artisan test tests/Feature/Processing/MediaOutboxModelTest.php`  
Expected: FAIL with missing table `media_outbox_messages` or missing class `MediaOutboxMessage`.

- [ ] **Step 3: Implement Migration, OutboxStatus Enum, and MediaOutboxMessage Model**

1. Create `panel/app/Domain/Processing/Enums/OutboxStatus.php`:
   - String-backed enum: `PENDING = 'pending'`, `PUBLISHED = 'published'`, `FAILED = 'failed'`.
   - Uses `DevWizardHQ\Enumify\Concerns\EnumHelpers` and implements `DevWizardHQ\Enumify\Contracts\HasLabels`.

2. Create `panel/database/migrations/2026_11_13_000001_create_media_outbox_messages_table.php`:
   - Columns: `uuid('id')->primary()`, `string('event_type')`, `string('aggregate_type')`, `uuid('aggregate_id')->index()`, `jsonb('payload')`, `string('status')->default('pending')->index()`, `integer('attempts')->default(0)`, `text('error_message')->nullable()`, `timestamp('published_at')->nullable()`, `timestamps()`.

3. Create `panel/app/Domain/Processing/Models/MediaOutboxMessage.php`:
   - `declare(strict_types=1);`, `final class MediaOutboxMessage extends Model`.
   - Uses `HasUuids`.
   - Casts: `status => OutboxStatus::class`, `payload => 'array'`, `published_at => 'datetime'`, `attempts => 'integer'`.
   - Scopes: `scopePending(Builder $query)`, `scopePublished(Builder $query)`, `scopeFailed(Builder $query)`.
   - Helper methods: `markPublished(): void`, `markFailed(string $error): void`.

- [ ] **Step 4: Run migration and test to verify it passes**

Run:
```bash
docker compose exec -T panel php artisan migrate
docker compose exec -T panel php artisan test tests/Feature/Processing/MediaOutboxModelTest.php
```
Expected: PASS with 3 passing tests.

- [ ] **Step 5: Commit**

```bash
git add panel/database/migrations/ panel/app/Domain/Processing/ panel/tests/Feature/Processing/
git commit -m "feat(processing): create transactional outbox schema and domain model"
```

---

### Task 2: Outbox Hook on Video Upload Completion

**Files:**
- Modify: `panel/app/Domain/Uploads/Services/MinioMultipartUploadService.php`
- Modify: `panel/tests/Feature/Upload/MinioMultipartUploadServiceTest.php`
- Create: `panel/tests/Feature/Processing/UploadCompletionOutboxTest.php`

**Interfaces:**
- Consumes: `UploadSession`, `VideoAsset`, `MediaOutboxMessage`.
- Produces: Atomically creates `MediaOutboxMessage` with `event_type = 'video.uploaded'` and transitions `Video` status to `VideoStatus::QUEUED` upon `completeUpload()`.

- [ ] **Step 1: Write failing test for upload completion creating outbox message and queuing video**

Create `panel/tests/Feature/Processing/UploadCompletionOutboxTest.php`:
```php
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
        $video = Video::factory()->forTenant($tenant)->create([
            'user_id' => $user->id,
            'status' => VideoStatus::UPLOADING,
        ]);

        $session = UploadSession::create([
            'tenant_id' => $tenant->id,
            'video_id' => $video->id,
            'upload_id' => 'upload-12345',
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T panel php artisan test tests/Feature/Processing/UploadCompletionOutboxTest.php`  
Expected: FAIL with `VideoStatus::UPLOADED` does not match `VideoStatus::QUEUED` or outbox message not found.

- [ ] **Step 3: Modify MinioMultipartUploadService to queue video and record outbox message**

In `panel/app/Domain/Uploads/Services/MinioMultipartUploadService.php`:
1. In `completeUpload()`, inside the `DB::transaction()`:
   - Update `$video->update(['status' => VideoStatus::QUEUED]);`
   - Insert `MediaOutboxMessage::create([...])`:
     - `event_type => 'video.uploaded'`
     - `aggregate_type => 'video'`
     - `aggregate_id => $video->id`
     - `payload => [`
         `'version' => '1.0',`
         `'job_id' => (string) Str::uuid(),`
         `'tenant_id' => $session->tenant_id,`
         `'video_id' => $video->id,`
         `'source_bucket' => $bucket,`
         `'source_key' => $session->object_key,`
         `'created_at' => now()->toIso8601String(),`
       `]`
2. Adjust any existing tests in `MinioMultipartUploadServiceTest.php` that asserted `VideoStatus::UPLOADED` to expect `VideoStatus::QUEUED`.

- [ ] **Step 4: Run tests to verify they pass**

Run:
```bash
docker compose exec -T panel php artisan test tests/Feature/Processing/UploadCompletionOutboxTest.php
docker compose exec -T panel php artisan test tests/Feature/Upload/MinioMultipartUploadServiceTest.php
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add panel/app/Domain/Uploads/Services/MinioMultipartUploadService.php panel/tests/
git commit -m "feat(processing): dispatch outbox message and queue video on upload completion"
```

---

### Task 3: RabbitMQ Configuration, AMQP Publisher Service & Artisan Command

**Files:**
- Modify: `panel/composer.json`
- Create: `panel/config/rabbitmq.php`
- Modify: `panel/.env`
- Modify: `panel/.env.example`
- Create: `panel/app/Domain/Processing/Services/RabbitMQPublisherService.php`
- Create: `panel/app/Console/Commands/Processing/PublishOutboxMessagesCommand.php`
- Test: `panel/tests/Feature/Processing/RabbitMQPublisherServiceTest.php`
- Test: `panel/tests/Feature/Processing/PublishOutboxMessagesCommandTest.php`

**Interfaces:**
- Consumes: `php-amqplib/php-amqplib`, `rabbitmq` container connection, `MediaOutboxMessage`.
- Produces: `RabbitMQPublisherService::publish(string $exchange, string $routingKey, array $message): void`, topology declaration (`media.direct`, `media.jobs`, `media.dlx`, `media.jobs.dlq`), and `php artisan media:publish-outbox` command.

- [ ] **Step 1: Install php-amqplib and add RabbitMQ configuration**

1. Install `php-amqplib/php-amqplib` in panel:
   Run: `docker compose exec -T panel composer require php-amqplib/php-amqplib`
2. Create `panel/config/rabbitmq.php`:
```php
<?php

declare(strict_types=1);

return [
    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    'exchange' => env('RABBITMQ_EXCHANGE', 'media.direct'),
    'queue' => env('RABBITMQ_QUEUE', 'media.jobs'),
    'routing_key' => env('RABBITMQ_ROUTING_KEY', 'media.process'),
    'dead_letter_exchange' => env('RABBITMQ_DLX', 'media.dlx'),
    'dead_letter_queue' => env('RABBITMQ_DLQ', 'media.jobs.dlq'),
    'dead_letter_routing_key' => env('RABBITMQ_DLQ_ROUTING_KEY', 'media.dlq'),
];
```
3. Update `panel/.env` and `panel/.env.example` with `RABBITMQ_HOST=rabbitmq`, `RABBITMQ_PORT=5672`, `RABBITMQ_USER=guest`, `RABBITMQ_PASSWORD=guest`.

- [ ] **Step 2: Write failing test for RabbitMQPublisherService and Outbox Command**

Create `panel/tests/Feature/Processing/PublishOutboxMessagesCommandTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Processing;

use App\Domain\Processing\Enums\OutboxStatus;
use App\Domain\Processing\Models\MediaOutboxMessage;
use App\Domain\Processing\Services\RabbitMQPublisherService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class PublishOutboxMessagesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_publishes_pending_messages_and_marks_them_published(): void
    {
        $message = MediaOutboxMessage::create([
            'event_type' => 'video.uploaded',
            'aggregate_type' => 'video',
            'aggregate_id' => '019324c3-e836-7db0-bd1b-c40c83a5316e',
            'payload' => ['video_id' => '019324c3-e836-7db0-bd1b-c40c83a5316e'],
        ]);

        $mockPublisher = Mockery::mock(RabbitMQPublisherService::class);
        $mockPublisher->shouldReceive('publish')
            ->once()
            ->with('media.direct', 'media.process', Mockery::on(function ($payload) use ($message) {
                return $payload['video_id'] === '019324c3-e836-7db0-bd1b-c40c83a5316e';
            }));

        $this->app->instance(RabbitMQPublisherService::class, $mockPublisher);

        $this->artisan('media:publish-outbox')
            ->assertSuccessful();

        $this->assertEquals(OutboxStatus::PUBLISHED, $message->fresh()->status);
        $this->assertNotNull($message->fresh()->published_at);
    }

    public function test_it_handles_publisher_exceptions_gracefully_and_marks_failed(): void
    {
        $message = MediaOutboxMessage::create([
            'event_type' => 'video.uploaded',
            'aggregate_type' => 'video',
            'aggregate_id' => '019324c3-e836-7db0-bd1b-c40c83a5316e',
            'payload' => ['video_id' => 'test'],
        ]);

        $mockPublisher = Mockery::mock(RabbitMQPublisherService::class);
        $mockPublisher->shouldReceive('publish')
            ->once()
            ->andThrow(new Exception('RabbitMQ connection lost'));

        $this->app->instance(RabbitMQPublisherService::class, $mockPublisher);

        $this->artisan('media:publish-outbox')
            ->assertSuccessful();

        $this->assertEquals(OutboxStatus::FAILED, $message->fresh()->status);
        $this->assertEquals(1, $message->fresh()->attempts);
        $this->assertStringContainsString('RabbitMQ connection lost', $message->fresh()->error_message);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `docker compose exec -T panel php artisan test tests/Feature/Processing/PublishOutboxMessagesCommandTest.php`  
Expected: FAIL with `Command "media:publish-outbox" is not defined` or missing service.

- [ ] **Step 4: Implement RabbitMQPublisherService and PublishOutboxMessagesCommand**

1. Create `panel/app/Domain/Processing/Services/RabbitMQPublisherService.php`:
   - Methods:
     - `setupTopology()`: Declares DLX (`media.dlx`, type: `direct`), DLQ (`media.jobs.dlq`), binds DLQ to DLX. Declares main exchange (`media.direct`, type: `direct`), main queue (`media.jobs`, durable with arguments: `x-dead-letter-exchange` => `media.dlx`, `x-dead-letter-routing-key` => `media.dlq`). Binds main queue to main exchange with routing key `media.process`.
     - `publish(string $exchange, string $routingKey, array $message): void`: Publishes AMQPMessage with persistent delivery mode (2) and JSON body.
     - Connection management with try/finally to close channels and AMQPStreamConnection.
2. Create `panel/app/Console/Commands/Processing/PublishOutboxMessagesCommand.php`:
   - Signature: `media:publish-outbox {--limit=100}`
   - Description: `Publish pending outbox messages to RabbitMQ broker`.
   - Iterates `MediaOutboxMessage::pending()->limit($limit)->get()`.
   - Publishes each message, calls `$msg->markPublished()` on success, or catches exception and calls `$msg->markFailed($e->getMessage())`.

- [ ] **Step 5: Run tests to verify they pass**

Run: `docker compose exec -T panel php artisan test tests/Feature/Processing/PublishOutboxMessagesCommandTest.php`  
Expected: PASS with 2 passing tests.

- [ ] **Step 6: Commit**

```bash
git add panel/config/rabbitmq.php panel/app/Domain/Processing/Services/ panel/app/Console/Commands/Processing/ panel/tests/
git commit -m "feat(processing): implement rabbitmq publisher service and outbox artisan command"
```

---

### Task 4: Secure Internal Callback API in Laravel

**Files:**
- Modify: `panel/config/services.php`
- Modify: `panel/.env`
- Modify: `panel/.env.example`
- Create: `panel/app/Http/Middleware/VerifyInternalSecret.php`
- Create: `panel/app/Http/Requests/Internal/ProcessingCallbackRequest.php`
- Create: `panel/app/Http/Controllers/Api/Internal/ProcessingCallbackController.php`
- Modify: `panel/routes/api/v1.php`
- Test: `panel/tests/Feature/Api/Internal/ProcessingCallbackTest.php`

**Interfaces:**
- Consumes: HTTP request to `POST /api/internal/v1/processing/callback` with `X-Internal-Secret` header.
- Produces: Validates callback payload, updates `Video` (`status`, `duration_seconds`, `metadata`), returns JSON response `{ "success": true, "video_id": "..." }`.

- [ ] **Step 1: Write failing tests for internal callback authentication and processing state transitions**

Create `panel/tests/Feature/Api/Internal/ProcessingCallbackTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Internal;

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\Video;
use App\Enums\VideoStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProcessingCallbackTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-internal-secret-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.internal.secret' => $this->secret]);
    }

    public function test_it_rejects_unauthorized_callback_requests(): void
    {
        $response = $this->postJson('/api/v1/internal/processing/callback', [
            'video_id' => '019324c3-e836-7db0-bd1b-c40c83a5316e',
            'status' => 'probing',
        ]);

        $response->assertStatus(401);
    }

    public function test_it_transitions_video_to_probing(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $video = Video::factory()->forTenant($tenant)->create([
            'user_id' => $user->id,
            'status' => VideoStatus::QUEUED,
        ]);

        $response = $this->withHeaders(['X-Internal-Secret' => $this->secret])
            ->postJson('/api/v1/internal/processing/callback', [
                'job_id' => '019324c4-7bf3-7521-9989-d419b4ef08f1',
                'video_id' => $video->id,
                'status' => 'probing',
            ]);

        $response->assertOk();
        $this->assertEquals(VideoStatus::PROBING, $video->fresh()->status);
    }

    public function test_it_updates_video_metadata_and_duration_on_probed(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $video = Video::factory()->forTenant($tenant)->create([
            'user_id' => $user->id,
            'status' => VideoStatus::PROBING,
        ]);

        $metadata = [
            'format' => 'mov,mp4',
            'size_bytes' => 1048576,
            'bitrate' => 1200000,
            'video' => [
                'codec' => 'h264',
                'width' => 1920,
                'height' => 1080,
                'fps' => 30.0,
            ],
            'audio' => [
                'codec' => 'aac',
                'channels' => 2,
            ],
        ];

        $response = $this->withHeaders(['X-Internal-Secret' => $this->secret])
            ->postJson('/api/v1/internal/processing/callback', [
                'job_id' => '019324c4-7bf3-7521-9989-d419b4ef08f1',
                'video_id' => $video->id,
                'status' => 'probed',
                'duration_seconds' => 42,
                'metadata' => $metadata,
            ]);

        $response->assertOk();
        $fresh = $video->fresh();
        $this->assertEquals(42, $fresh->duration_seconds);
        $this->assertEquals('h264', $fresh->metadata['video']['codec']);
        $this->assertEquals(1920, $fresh->metadata['video']['width']);
    }

    public function test_it_marks_video_failed_on_error(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $video = Video::factory()->forTenant($tenant)->create([
            'user_id' => $user->id,
            'status' => VideoStatus::PROBING,
        ]);

        $response = $this->withHeaders(['X-Internal-Secret' => $this->secret])
            ->postJson('/api/v1/internal/processing/callback', [
                'job_id' => '019324c4-7bf3-7521-9989-d419b4ef08f1',
                'video_id' => $video->id,
                'status' => 'failed',
                'error' => 'Unsupported container format or corrupt file header',
            ]);

        $response->assertOk();
        $fresh = $video->fresh();
        $this->assertEquals(VideoStatus::FAILED, $fresh->status);
        $this->assertEquals('Unsupported container format or corrupt file header', $fresh->metadata['error']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T panel php artisan test tests/Feature/Api/Internal/ProcessingCallbackTest.php`  
Expected: FAIL with 404 Route not found.

- [ ] **Step 3: Implement Internal Secret Middleware, Controller, and Route**

1. In `panel/config/services.php`:
   ```php
   'internal' => [
       'secret' => env('INTERNAL_API_SECRET', 'internal-worker-secret-key-12345'),
   ],
   ```
2. Create `panel/app/Http/Middleware/VerifyInternalSecret.php`:
   - Checks `hash_equals(config('services.internal.secret'), $request->header('X-Internal-Secret', ''))`.
   - Returns 401 Unauthorized if invalid.
3. Create `panel/app/Http/Requests/Internal/ProcessingCallbackRequest.php`:
   - Validates:
     - `job_id`: required|uuid
     - `video_id`: required|uuid|exists:videos,id
     - `status`: required|in:probing,probed,failed
     - `duration_seconds`: nullable|integer|min:0
     - `metadata`: nullable|array
     - `error`: nullable|string|max:2000
4. Create `panel/app/Http/Controllers/Api/Internal/ProcessingCallbackController.php`:
   - Handles `probing`: sets video status to `VideoStatus::PROBING`.
   - Handles `probed`: sets `duration_seconds`, merges `metadata`, leaves video ready for next phase.
   - Handles `failed`: sets video status to `VideoStatus::FAILED`, records error in metadata.
   - Returns JSON `['success' => true, 'video_id' => $video->id]`.
5. Register route in `panel/routes/api/v1.php`:
   ```php
   Route::prefix('internal')->middleware(\App\Http\Middleware\VerifyInternalSecret::class)->group(function (): void {
       Route::post('/processing/callback', \App\Http\Controllers\Api\Internal\ProcessingCallbackController::class);
   });
   ```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T panel php artisan test tests/Feature/Api/Internal/ProcessingCallbackTest.php`  
Expected: PASS with 4 passing tests.

- [ ] **Step 5: Commit**

```bash
git add panel/config/services.php panel/app/Http/Middleware/ panel/app/Http/Requests/Internal/ panel/app/Http/Controllers/Api/Internal/ panel/routes/api/v1.php panel/tests/Feature/Api/Internal/
git commit -m "feat(processing): implement secure internal callback api in laravel"
```

---

### Task 5: Go Worker Skeleton, Config, and MinIO Storage Client

**Files:**
- Create: `worker/go.mod`
- Create: `worker/go.sum`
- Create: `worker/internal/config/config.go`
- Create: `worker/internal/config/config_test.go`
- Create: `worker/internal/storage/minio.go`
- Create: `worker/internal/observability/logger.go`

**Interfaces:**
- Consumes: Environment variables (`RABBITMQ_URL`, `MINIO_ENDPOINT`, `MINIO_ACCESS_KEY`, `MINIO_SECRET_KEY`, `INTERNAL_CALLBACK_URL`, `INTERNAL_API_SECRET`).
- Produces: `config.Load() (*Config, error)`, `storage.NewMinioClient(cfg) (*MinioStorage, error)`, `storage.DownloadSourceFile(ctx, bucket, key, destPath) error`.

- [ ] **Step 1: Initialize Go module in worker directory**

In `worker/`:
Run commands inside docker or create `worker/go.mod`:
```go
module wiz-media-worker

go 1.23

require (
	github.com/minio/minio-go/v7 v7.0.70
	github.com/rabbitmq/amqp091-go v1.10.0
)
```

- [ ] **Step 2: Write unit test for Config loader**

Create `worker/internal/config/config_test.go`:
```go
package config_test

import (
	"os"
	"testing"
	"wiz-media-worker/internal/config"
)

func TestLoadConfig_DefaultsAndOverrides(t *testing.T) {
	os.Setenv("RABBITMQ_URL", "amqp://test:test@localhost:5672/")
	os.Setenv("INTERNAL_API_SECRET", "super-secret")
	defer os.Unsetenv("RABBITMQ_URL")
	defer os.Unsetenv("INTERNAL_API_SECRET")

	cfg, err := config.Load()
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}

	if cfg.RabbitMQURL != "amqp://test:test@localhost:5672/" {
		t.Errorf("unexpected RabbitMQURL: %s", cfg.RabbitMQURL)
	}
	if cfg.InternalAPISecret != "super-secret" {
		t.Errorf("unexpected InternalAPISecret: %s", cfg.InternalAPISecret)
	}
	if cfg.Concurrency != 2 {
		t.Errorf("expected default concurrency 2, got %d", cfg.Concurrency)
	}
}
```

- [ ] **Step 3: Implement Config, Logger, and MinIO storage client**

1. `worker/internal/config/config.go`:
   - Struct `Config`: `RabbitMQURL`, `RabbitMQQueue`, `MinioEndpoint`, `MinioAccessKey`, `MinioSecretKey`, `MinioUseSSL`, `InternalCallbackURL`, `InternalAPISecret`, `Concurrency`, `TempDir`.
   - Reads environment with sensible local defaults matching `docker-compose.yml`.
2. `worker/internal/observability/logger.go`:
   - Sets up `slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{Level: slog.LevelInfo}))`.
3. `worker/internal/storage/minio.go`:
   - Wrapper around `minio.New()` with credentials.
   - Method `DownloadToFile(ctx context.Context, bucket, objectKey, localPath string) error`: streams object from MinIO to local temporary file.

- [ ] **Step 4: Verify Go build and run tests using Docker builder container**

Run:
```bash
docker run --rm -v "D:\Playground\wiz-video-platform\worker:/app" -w /app golang:1.23-alpine go test ./internal/config/...
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add worker/
git commit -m "feat(worker): setup go worker module, config, and minio storage client"
```

---

### Task 6: FFprobe Media Inspector in Go

**Files:**
- Create: `worker/internal/probe/probe.go`
- Create: `worker/internal/probe/types.go`
- Create: `worker/internal/probe/probe_test.go`

**Interfaces:**
- Consumes: Local media file path and `ffprobe` executable.
- Produces: `probe.Inspect(ctx context.Context, filePath string) (*ProbeResult, error)`.
- `ProbeResult` includes: `DurationSeconds int`, `Metadata map[string]any` (containing video codec, width, height, fps, bitrate, audio codec, channels, sample rate).

- [ ] **Step 1: Write unit test for FFprobe output parser**

Create `worker/internal/probe/probe_test.go`:
```go
package probe_test

import (
	"context"
	"testing"
	"wiz-media-worker/internal/probe"
)

func TestParseProbeJSON(t *testing.T) {
	sampleJSON := []byte(`{
		"format": {
			"filename": "sample.mp4",
			"format_name": "mov,mp4,m4a,3gp,3g2,mj2",
			"duration": "128.500000",
			"size": "52428800",
			"bit_rate": "3263272"
		},
		"streams": [
			{
				"codec_type": "video",
				"codec_name": "h264",
				"profile": "High",
				"width": 1920,
				"height": 1080,
				"r_frame_rate": "30/1",
				"pix_fmt": "yuv420p"
			},
			{
				"codec_type": "audio",
				"codec_name": "aac",
				"channels": 2,
				"sample_rate": "48000",
				"bit_rate": "192000"
			}
		]
	}`)

	result, err := probe.ParseJSON(sampleJSON)
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}

	if result.DurationSeconds != 128 {
		t.Errorf("expected duration 128, got %d", result.DurationSeconds)
	}
	if result.Format != "mov,mp4,m4a,3gp,3g2,mj2" {
		t.Errorf("unexpected format: %s", result.Format)
	}
	if result.Video.Codec != "h264" || result.Video.Width != 1920 || result.Video.Height != 1080 {
		t.Errorf("unexpected video stream data: %+v", result.Video)
	}
	if result.Audio.Codec != "aac" || result.Audio.Channels != 2 {
		t.Errorf("unexpected audio stream data: %+v", result.Audio)
	}
}

func TestInspect_FailsOnMissingFile(t *testing.T) {
	inspector := probe.NewInspector("ffprobe")
	_, err := inspector.Inspect(context.Background(), "/nonexistent/video.mp4")
	if err == nil {
		t.Fatalf("expected error on nonexistent file, got nil")
	}
}
```

- [ ] **Step 2: Run test using Docker builder container to verify it fails**

Run:
```bash
docker run --rm -v "D:\Playground\wiz-video-platform\worker:/app" -w /app golang:1.23-alpine go test ./internal/probe/...
```
Expected: FAIL with package or functions not defined.

- [ ] **Step 3: Implement FFprobe Inspector**

1. Create `worker/internal/probe/types.go`:
   - Go structs for raw FFprobe JSON deserialization (`FFprobeOutput`, `FFprobeFormat`, `FFprobeStream`).
   - Clean domain structs: `ProbeResult`, `VideoStreamInfo`, `AudioStreamInfo`.
2. Create `worker/internal/probe/probe.go`:
   - `NewInspector(binPath string) *Inspector` (default: `"ffprobe"`).
   - `Inspect(ctx context.Context, filePath string) (*ProbeResult, error)`:
     - Executes `exec.CommandContext(ctx, binPath, "-v", "quiet", "-print_format", "json", "-show_format", "-show_streams", filePath)`.
     - Validates stdout and stderr.
     - Parses JSON using `ParseJSON([]byte) (*ProbeResult, error)`.
     - Computes frame rate from `"30/1"` or `"29.97"` strings safely.

- [ ] **Step 4: Run test to verify it passes**

Run:
```bash
docker run --rm -v "D:\Playground\wiz-video-platform\worker:/app" -w /app golang:1.23-alpine go test ./internal/probe/...
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add worker/internal/probe/
git commit -m "feat(worker): implement ffprobe media inspector and json parser in go"
```

---

### Task 7: HTTP Callback Client, Pipeline Orchestrator, and RabbitMQ Consumer

**Files:**
- Create: `worker/internal/callback/client.go`
- Create: `worker/internal/callback/client_test.go`
- Create: `worker/internal/pipeline/job.go`
- Create: `worker/internal/pipeline/pipeline.go`
- Create: `worker/internal/consumer/consumer.go`
- Create: `worker/cmd/worker/main.go`

**Interfaces:**
- Consumes: RabbitMQ messages, MinIO storage client, FFprobe inspector.
- Produces: End-to-end processing pipeline that consumes message &rarr; callbacks Laravel `probing` &rarr; downloads video &rarr; probes media &rarr; callbacks Laravel `probed` (or `failed`) &rarr; acks message &rarr; cleans up temp file.

- [ ] **Step 1: Write test for Callback Client**

Create `worker/internal/callback/client_test.go`:
```go
package callback_test

import (
	"context"
	"net/http"
	"net/http/httptest"
	"testing"
	"wiz-media-worker/internal/callback"
)

func TestCallbackClient_SendsInternalSecretHeader(t *testing.T) {
	var receivedHeader string
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		receivedHeader = r.Header.Get("X-Internal-Secret")
		w.WriteHeader(http.StatusOK)
		w.Write([]byte(`{"success":true}`))
	}))
	defer server.Close()

	client := callback.NewClient(server.URL, "my-secret-key")
	err := client.SendStatus(context.Background(), callback.Payload{
		JobID:   "019324c4-7bf3-7521-9989-d419b4ef08f1",
		VideoID: "019324c3-e836-7db0-bd1b-c40c83a5316e",
		Status:  "probing",
	})

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if receivedHeader != "my-secret-key" {
		t.Errorf("expected header 'my-secret-key', got '%s'", receivedHeader)
	}
}
```

- [ ] **Step 2: Run test using Docker builder to verify it fails**

Run:
```bash
docker run --rm -v "D:\Playground\wiz-video-platform\worker:/app" -w /app golang:1.23-alpine go test ./internal/callback/...
```
Expected: FAIL with package not found.

- [ ] **Step 3: Implement Callback Client, Pipeline, Consumer, and main.go**

1. `worker/internal/callback/client.go`:
   - `Client` with `http.Client` (timeout: 10s).
   - Method `SendStatus(ctx context.Context, payload Payload) error`: POST to callback URL with `X-Internal-Secret` header, retry up to 3 times on 5xx with exponential backoff.
2. `worker/internal/pipeline/job.go`:
   - Struct `MediaJob`: `Version`, `JobID`, `TenantID`, `VideoID`, `SourceBucket`, `SourceKey`, `CreatedAt`.
3. `worker/internal/pipeline/pipeline.go`:
   - `Process(ctx context.Context, job MediaJob) error`:
     - Calls callback `status: probing`.
     - Creates local temporary file in `TempDir`.
     - Downloads from MinIO via `storage.DownloadToFile`.
     - Executes `probe.Inspect`.
     - On probe success: calls callback `status: probed` with `duration_seconds` and `metadata`.
     - On probe failure: calls callback `status: failed` with error.
     - Always removes temporary file (`defer os.Remove(tempFile)`).
4. `worker/internal/consumer/consumer.go`:
   - Uses `github.com/rabbitmq/amqp091-go`.
   - Connects to RabbitMQ with reconnect loop on disconnect.
   - Sets QoS prefetch count to `cfg.Concurrency`.
   - Consumes from `cfg.RabbitMQQueue`.
   - Parses `MediaJob` JSON:
     - If malformed: logs error and NACK with `requeue = false` (routed to DLQ).
     - Dispatches to pipeline.
     - ACKs message on completion.
5. `worker/cmd/worker/main.go`:
   - Initializes config, logger, MinIO client, probe inspector, callback client, and consumer.
   - Listens for `os.Interrupt`, `syscall.SIGTERM` for graceful shutdown.

- [ ] **Step 4: Run all Go unit tests**

Run:
```bash
docker run --rm -v "D:\Playground\wiz-video-platform\worker:/app" -w /app golang:1.23-alpine go test ./...
```
Expected: ALL PASS.

- [ ] **Step 5: Commit**

```bash
git add worker/
git commit -m "feat(worker): implement callback client, media pipeline, rabbitmq consumer, and main entrypoint"
```

---

### Task 8: Dockerfile & Docker Compose Integration with End-to-End Verification

**Files:**
- Create: `worker/Dockerfile`
- Create: `worker/.dockerignore`
- Modify: `docker-compose.yml`
- Modify: `panel/.env`
- Modify: `panel/.env.example`
- Create: `panel/tests/Feature/Processing/EndToEndMediaProcessingTest.php`

**Interfaces:**
- Consumes: Docker Compose networking, `wiz_rabbitmq`, `wiz_minio`, `wiz_panel`.
- Produces: Running `wiz_worker` container executing Go binary with `ffmpeg` + `ffprobe` installed, end-to-end integration test proving complete flow from upload to probed metadata.

- [ ] **Step 1: Create Dockerfile for Go Worker**

Create `worker/Dockerfile`:
```dockerfile
FROM golang:1.23-alpine AS builder

WORKDIR /app

COPY go.mod go.sum ./
RUN go mod download

COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -ldflags="-w -s" -o /worker ./cmd/worker

FROM alpine:3.20

RUN apk add --no-cache ffmpeg ca-certificates tzdata

WORKDIR /app
COPY --from=builder /worker /app/worker

ENV TEMP_DIR=/tmp/media

RUN mkdir -p /tmp/media && adduser -D -u 1000 appuser && chown -R appuser:appuser /app /tmp/media
USER appuser

ENTRYPOINT ["/app/worker"]
```

Create `worker/.dockerignore`:
```
.git
.idea
.vscode
tmp
```

- [ ] **Step 2: Add worker service to docker-compose.yml**

In `docker-compose.yml`, add the `worker` service:
```yaml
  worker:
    build:
      context: ./worker
      dockerfile: Dockerfile
    container_name: wiz_worker
    restart: unless-stopped
    environment:
      RABBITMQ_URL: amqp://guest:guest@rabbitmq:5672/
      RABBITMQ_QUEUE: media.jobs
      MINIO_ENDPOINT: minio:9000
      MINIO_ACCESS_KEY: minioadmin
      MINIO_SECRET_KEY: minioadmin
      MINIO_USE_SSL: "false"
      INTERNAL_CALLBACK_URL: http://panel:8000/api/v1/internal/processing/callback
      INTERNAL_API_SECRET: "${INTERNAL_API_SECRET:-internal-worker-secret-key-12345}"
      CONCURRENCY: 2
    depends_on:
      - rabbitmq
      - minio
      - panel
```

- [ ] **Step 3: Build and start worker container**

Run:
```bash
docker compose build worker
docker compose up -d worker
docker compose ps
```
Expected: `wiz_worker` is in `Up` status.

- [ ] **Step 4: Write and run end-to-end integration test in Laravel**

Create `panel/tests/Feature/Processing/EndToEndMediaProcessingTest.php`:
```php
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class EndToEndMediaProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_pipeline_outbox_publish_and_callback_simulation(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $video = Video::factory()->forTenant($tenant)->create([
            'user_id' => $user->id,
            'status' => VideoStatus::UPLOADING,
        ]);

        $session = UploadSession::create([
            'tenant_id' => $tenant->id,
            'video_id' => $video->id,
            'upload_id' => 'mock-upload-id',
            'object_key' => "tenants/{$tenant->id}/videos/{$video->id}/source.mp4",
            'part_size' => 5242880,
            'total_parts' => 1,
            'status' => UploadSessionStatus::PENDING,
            'expires_at' => now()->addHours(24),
        ]);

        // 1. Simulate video upload completion & outbox insertion
        $outbox = MediaOutboxMessage::create([
            'event_type' => 'video.uploaded',
            'aggregate_type' => 'video',
            'aggregate_id' => $video->id,
            'payload' => [
                'version' => '1.0',
                'job_id' => '019324c4-7bf3-7521-9989-d419b4ef08f1',
                'tenant_id' => $tenant->id,
                'video_id' => $video->id,
                'source_bucket' => 'videos-original',
                'source_key' => $session->object_key,
                'created_at' => now()->toIso8601String(),
            ],
            'status' => OutboxStatus::PENDING,
        ]);
        $video->update(['status' => VideoStatus::QUEUED]);

        $this->assertEquals(VideoStatus::QUEUED, $video->fresh()->status);
        $this->assertEquals(OutboxStatus::PENDING, $outbox->status);

        // 2. Publish outbox message
        $this->artisan('media:publish-outbox')->assertSuccessful();
        $this->assertEquals(OutboxStatus::PUBLISHED, $outbox->fresh()->status);

        // 3. Simulate Go worker callback
        $response = $this->withHeaders(['X-Internal-Secret' => config('services.internal.secret')])
            ->postJson('/api/v1/internal/processing/callback', [
                'job_id' => '019324c4-7bf3-7521-9989-d419b4ef08f1',
                'video_id' => $video->id,
                'status' => 'probed',
                'duration_seconds' => 64,
                'metadata' => [
                    'format' => 'mp4',
                    'video' => ['codec' => 'h264', 'width' => 1920, 'height' => 1080],
                ],
            ]);

        $response->assertOk();
        $updatedVideo = $video->fresh();
        $this->assertEquals(64, $updatedVideo->duration_seconds);
        $this->assertEquals('h264', $updatedVideo->metadata['video']['codec']);
    }
}
```

Run test:
```bash
docker compose exec -T panel php artisan test tests/Feature/Processing/EndToEndMediaProcessingTest.php
```
Expected: PASS.

- [ ] **Step 5: Inspect worker container logs to verify clean startup**

Run:
```bash
docker compose logs --tail=20 worker
```
Expected: Structured log showing worker connected to RabbitMQ and waiting for messages on queue `media.jobs`.

- [ ] **Step 6: Commit**

```bash
git add docker-compose.yml worker/Dockerfile worker/.dockerignore panel/tests/Feature/Processing/
git commit -m "feat(processing): integrate go worker container into docker-compose and verify end-to-end pipeline"
```
