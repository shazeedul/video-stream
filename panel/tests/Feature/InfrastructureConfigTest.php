<?php

declare(strict_types=1);

test('postgresql connection configuration is properly defined', function (): void {
    $config = config('database.connections.pgsql');

    expect($config['driver'])->toBe('pgsql')
        ->and($config['search_path'])->toBe('public')
        ->and($config['sslmode'])->toBe('prefer');
});

test('docker compose file exists and defines required MVP services', function (): void {
    $composePath = base_path('../docker-compose.yml');
    expect(file_exists($composePath))->toBeTrue();

    $content = file_get_contents($composePath);
    expect($content)
        ->toContain('postgres:')
        ->toContain('redis:')
        ->toContain('rabbitmq:')
        ->toContain('minio:')
        ->toContain('nginx:');
});
