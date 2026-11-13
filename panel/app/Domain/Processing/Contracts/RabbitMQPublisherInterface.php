<?php

declare(strict_types=1);

namespace App\Domain\Processing\Contracts;

interface RabbitMQPublisherInterface
{
    /**
     * Declare exchanges, queues, DLX, and bindings.
     */
    public function setupTopology(): void;

    /**
     * Publish a message to RabbitMQ.
     *
     * @param  array<string, mixed>  $message
     */
    public function publish(string $exchange, string $routingKey, array $message): void;
}
