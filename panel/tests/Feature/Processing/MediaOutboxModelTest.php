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

    public function test_it_scopes_published_and_failed_messages(): void
    {
        $pending = MediaOutboxMessage::create([
            'event_type' => 'video.uploaded',
            'aggregate_type' => 'video',
            'aggregate_id' => '019324c3-e836-7db0-bd1b-c40c83a5316a',
            'payload' => ['foo' => 'bar'],
            'status' => OutboxStatus::PENDING,
        ]);

        $published = MediaOutboxMessage::create([
            'event_type' => 'video.uploaded',
            'aggregate_type' => 'video',
            'aggregate_id' => '019324c3-e836-7db0-bd1b-c40c83a5316b',
            'payload' => ['foo' => 'bar'],
            'status' => OutboxStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        $failed = MediaOutboxMessage::create([
            'event_type' => 'video.uploaded',
            'aggregate_type' => 'video',
            'aggregate_id' => '019324c3-e836-7db0-bd1b-c40c83a5316c',
            'payload' => ['foo' => 'bar'],
            'status' => OutboxStatus::FAILED,
            'error_message' => 'Publish failed',
        ]);

        $publishedResults = MediaOutboxMessage::published()->get();
        $this->assertTrue($publishedResults->contains($published));
        $this->assertFalse($publishedResults->contains($pending));
        $this->assertFalse($publishedResults->contains($failed));

        $failedResults = MediaOutboxMessage::failed()->get();
        $this->assertTrue($failedResults->contains($failed));
        $this->assertFalse($failedResults->contains($pending));
        $this->assertFalse($failedResults->contains($published));
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
