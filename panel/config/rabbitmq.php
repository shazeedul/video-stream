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
