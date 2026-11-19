<?php

declare(strict_types=1);

namespace App\Console\Commands\Processing;

use App\Domain\Processing\Contracts\RabbitMQPublisherInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Description('Declare and configure RabbitMQ exchanges, queues, and bindings')]
#[Signature('rabbitmq:setup')]
final class RabbitMQSetupCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RabbitMQPublisherInterface $publisher): int
    {
        $this->info('Configuring RabbitMQ topology...');

        try {
            $publisher->setupTopology();
            $this->info('RabbitMQ topology (exchanges, queues, and bindings) created successfully.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Failed to setup RabbitMQ topology: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
