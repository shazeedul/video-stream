<?php

declare(strict_types=1);

namespace App\Console\Commands\Processing;

use App\Domain\Processing\Contracts\RabbitMQPublisherInterface;
use App\Domain\Processing\Models\MediaOutboxMessage;
use Illuminate\Console\Command;
use Throwable;

final class PublishOutboxMessagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:publish-outbox {--limit=100 : Maximum number of messages to publish}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish pending outbox messages to RabbitMQ broker';

    /**
     * Execute the console command.
     */
    public function handle(RabbitMQPublisherInterface $publisher): int
    {
        $limit = (int) $this->option('limit');
        $messages = MediaOutboxMessage::pending()
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        if ($messages->isEmpty()) {
            $this->info('No pending outbox messages found.');

            return self::SUCCESS;
        }

        $exchange = (string) config('rabbitmq.exchange', 'media.direct');
        $routingKey = (string) config('rabbitmq.routing_key', 'media.process');

        $publishedCount = 0;
        $failedCount = 0;

        foreach ($messages as $message) {
            try {
                $publisher->publish($exchange, $routingKey, $message->payload);
                $message->markPublished();
                $publishedCount++;
            } catch (Throwable $e) {
                $message->markFailed($e->getMessage());
                $failedCount++;
                $this->error("Failed to publish message {$message->id}: {$e->getMessage()}");
            }
        }

        $this->info("Published {$publishedCount} message(s), {$failedCount} failed.");

        return self::SUCCESS;
    }
}
