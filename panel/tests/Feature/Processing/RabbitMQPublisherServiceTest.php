<?php

declare(strict_types=1);

namespace Tests\Feature\Processing;

use App\Domain\Processing\Contracts\RabbitMQPublisherInterface;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Tests\TestCase;

final class RabbitMQPublisherServiceTest extends TestCase
{
    public function test_it_can_setup_topology_and_publish_message(): void
    {
        /** @var RabbitMQPublisherInterface $publisher */
        $publisher = app(RabbitMQPublisherInterface::class);

        $publisher->setupTopology();

        $connection = new AMQPStreamConnection(
            (string) config('rabbitmq.host', 'rabbitmq'),
            (int) config('rabbitmq.port', 5672),
            (string) config('rabbitmq.user', 'guest'),
            (string) config('rabbitmq.password', 'guest'),
            (string) config('rabbitmq.vhost', '/'),
        );

        $channel = $connection->channel();

        try {
            $channel->queue_purge('media.jobs');

            $payload = [
                'test_id' => '019324c3-e836-7db0-bd1b-c40c83a5316e',
                'action' => 'test_publish',
            ];

            $publisher->publish('media.direct', 'media.process', $payload);

            $message = $channel->basic_get('media.jobs');
            $this->assertNotNull($message, 'Expected a message in media.jobs queue');

            $decoded = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame($payload, $decoded);

            $channel->basic_ack((int) $message->delivery_info['delivery_tag']);
        } finally {
            $channel->close();
            $connection->close();
        }
    }
}
