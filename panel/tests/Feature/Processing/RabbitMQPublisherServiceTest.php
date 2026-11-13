<?php

declare(strict_types=1);

namespace Tests\Feature\Processing;

use App\Domain\Processing\Services\RabbitMQPublisherService;
use Tests\TestCase;

final class RabbitMQPublisherServiceTest extends TestCase
{
    public function test_it_can_setup_topology_and_publish_message(): void
    {
        $publisher = app(RabbitMQPublisherService::class);

        $publisher->setupTopology();

        $publisher->publish('media.direct', 'media.process', [
            'test_id' => '019324c3-e836-7db0-bd1b-c40c83a5316e',
            'action' => 'test_publish',
        ]);

        $this->assertTrue(true);
    }
}
