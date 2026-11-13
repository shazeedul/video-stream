<?php

declare(strict_types=1);

namespace Tests\Feature\Processing;

use App\Domain\Processing\Contracts\RabbitMQPublisherInterface;
use App\Domain\Processing\Enums\OutboxStatus;
use App\Domain\Processing\Models\MediaOutboxMessage;
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

        $mockPublisher = Mockery::mock(RabbitMQPublisherInterface::class);
        $mockPublisher->shouldReceive('publish')
            ->once()
            ->with('media.direct', 'media.process', Mockery::on(function ($payload) {
                return $payload['video_id'] === '019324c3-e836-7db0-bd1b-c40c83a5316e';
            }));

        $this->app->instance(RabbitMQPublisherInterface::class, $mockPublisher);

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

        $mockPublisher = Mockery::mock(RabbitMQPublisherInterface::class);
        $mockPublisher->shouldReceive('publish')
            ->once()
            ->andThrow(new Exception('RabbitMQ connection lost'));

        $this->app->instance(RabbitMQPublisherInterface::class, $mockPublisher);

        $this->artisan('media:publish-outbox')
            ->assertSuccessful();

        $this->assertEquals(OutboxStatus::FAILED, $message->fresh()->status);
        $this->assertEquals(1, $message->fresh()->attempts);
        $this->assertStringContainsString('RabbitMQ connection lost', $message->fresh()->error_message);
    }
}
