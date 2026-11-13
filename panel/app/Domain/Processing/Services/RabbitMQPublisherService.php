<?php

declare(strict_types=1);

namespace App\Domain\Processing\Services;

use App\Domain\Processing\Contracts\RabbitMQPublisherInterface;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

final class RabbitMQPublisherService implements RabbitMQPublisherInterface
{
    private ?AMQPStreamConnection $connection = null;

    private bool $topologySetup = false;

    public function __construct(
        private readonly ?string $host = null,
        private readonly ?int $port = null,
        private readonly ?string $user = null,
        private readonly ?string $password = null,
        private readonly ?string $vhost = null,
    ) {}

    public function __destruct()
    {
        try {
            $this->close();
        } catch (Throwable) {
            // Suppress exceptions during shutdown to prevent fatal errors
        }
    }

    public function setupTopology(): void
    {
        $connection = $this->getConnection();
        $channel = $connection->channel();

        try {
            $dlx = (string) config('rabbitmq.dead_letter_exchange', 'media.dlx');
            $dlq = (string) config('rabbitmq.dead_letter_queue', 'media.jobs.dlq');
            $dlqRoutingKey = (string) config('rabbitmq.dead_letter_routing_key', 'media.dlq');

            $exchange = (string) config('rabbitmq.exchange', 'media.direct');
            $queue = (string) config('rabbitmq.queue', 'media.jobs');
            $routingKey = (string) config('rabbitmq.routing_key', 'media.process');

            // 1. Declare dead-letter exchange (direct, durable)
            $channel->exchange_declare($dlx, 'direct', false, true, false);

            // 2. Declare dead-letter queue (durable)
            $channel->queue_declare($dlq, false, true, false, false);

            // 3. Bind dead-letter queue to dead-letter exchange
            $channel->queue_bind($dlq, $dlx, $dlqRoutingKey);

            // 4. Declare main direct exchange (durable)
            $channel->exchange_declare($exchange, 'direct', false, true, false);

            // 5. Declare main queue with dead-letter configuration (durable)
            $args = new AMQPTable([
                'x-dead-letter-exchange' => $dlx,
                'x-dead-letter-routing-key' => $dlqRoutingKey,
            ]);
            $channel->queue_declare($queue, false, true, false, false, false, $args);

            // 6. Bind main queue to main direct exchange
            $channel->queue_bind($queue, $exchange, $routingKey);

            $this->topologySetup = true;
        } finally {
            $channel->close();
        }
    }

    public function publish(string $exchange, string $routingKey, array $message): void
    {
        if (! $this->topologySetup) {
            $this->setupTopology();
        }

        $connection = $this->getConnection();
        $channel = $connection->channel();

        try {
            $body = json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $amqpMessage = new AMQPMessage($body, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json',
            ]);

            $channel->basic_publish($amqpMessage, $exchange, $routingKey);
        } finally {
            $channel->close();
        }
    }

    public function close(): void
    {
        if ($this->connection !== null && $this->connection->isConnected()) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    private function getConnection(): AMQPStreamConnection
    {
        if ($this->connection === null || ! $this->connection->isConnected()) {
            $this->connection = new AMQPStreamConnection(
                $this->host ?? (string) config('rabbitmq.host', 'rabbitmq'),
                $this->port ?? (int) config('rabbitmq.port', 5672),
                $this->user ?? (string) config('rabbitmq.user', 'guest'),
                $this->password ?? (string) config('rabbitmq.password', 'guest'),
                $this->vhost ?? (string) config('rabbitmq.vhost', '/'),
            );
        }

        return $this->connection;
    }
}
