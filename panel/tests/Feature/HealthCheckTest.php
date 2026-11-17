<?php

declare(strict_types=1);

use App\Domain\Monitoring\Contracts\HealthCheckServiceInterface;
use Illuminate\Support\Facades\Redis;

beforeEach(function (): void {
    Redis::del('workers:active');
    Redis::del('worker:heartbeat:worker-test-1');
    Redis::del('worker:heartbeat:active-worker');
    Redis::del('worker:heartbeat:dead-worker');
    Redis::del('worker:heartbeat:worker-test-diag');
});

test('health returns 200 ok when db, redis, and background services are operational', function (): void {
    Redis::sadd('workers:active', 'worker-test-1');
    Redis::setex('worker:heartbeat:worker-test-1', 45, (string) json_encode([
        'id' => 'worker-test-1',
        'hostname' => 'worker-node-1',
        'timestamp' => time(),
    ], JSON_THROW_ON_ERROR));

    $response = $this->get('/health');

    $response->assertOk()
        ->assertJson([
            'status' => 'ok',
            'database' => 'ok',
            'redis' => 'ok',
        ]);
});

test('health returns 503 when database connection fails', function (): void {
    $mock = Mockery::mock(HealthCheckServiceInterface::class);
    $mock->shouldReceive('checkLiveness')->once()->andReturn([
        'status' => 'unhealthy',
        'database' => 'error',
        'redis' => 'ok',
    ]);
    $this->app->instance(HealthCheckServiceInterface::class, $mock);

    $response = $this->get('/health');

    $response->assertStatus(503)
        ->assertJson([
            'status' => 'unhealthy',
            'database' => 'error',
        ]);
});

test('health returns 503 when redis connection fails', function (): void {
    $mock = Mockery::mock(HealthCheckServiceInterface::class);
    $mock->shouldReceive('checkLiveness')->once()->andReturn([
        'status' => 'unhealthy',
        'database' => 'ok',
        'redis' => 'error',
    ]);
    $this->app->instance(HealthCheckServiceInterface::class, $mock);

    $response = $this->get('/health');

    $response->assertStatus(503)
        ->assertJson([
            'status' => 'unhealthy',
            'redis' => 'error',
        ]);
});

test('health returns 200 degraded when worker is offline', function (): void {
    // No active workers registered
    Redis::del('workers:active');

    $response = $this->get('/health');

    $response->assertOk()
        ->assertJson([
            'status' => 'degraded',
            'database' => 'ok',
            'redis' => 'ok',
            'workers' => 'error',
        ]);
});

test('health returns 200 degraded when rabbitmq is offline', function (): void {
    Redis::sadd('workers:active', 'worker-test-1');
    Redis::setex('worker:heartbeat:worker-test-1', 45, (string) json_encode([
        'id' => 'worker-test-1',
        'hostname' => 'worker-node-1',
        'timestamp' => time(),
    ], JSON_THROW_ON_ERROR));

    config(['rabbitmq.host' => '127.0.0.1', 'rabbitmq.port' => 59999]);

    $response = $this->get('/health');

    $response->assertOk()
        ->assertJson([
            'status' => 'degraded',
            'database' => 'ok',
            'redis' => 'ok',
            'rabbitmq' => 'error',
        ]);
});

test('admin health endpoint works with sanctum token authentication', function (): void {
    $admin = adminUser();
    Laravel\Sanctum\Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/admin/health');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'timestamp',
            'components',
        ]);
});

test('admin health endpoint requires authentication', function (): void {
    $response = $this->getJson('/api/v1/admin/health');

    $response->assertUnauthorized();
});

test('admin health endpoint requires admin panel permission', function (): void {
    $portalUser = portalUser();

    $response = $this->actingAs($portalUser)->getJson('/api/v1/admin/health');

    $response->assertForbidden();
});

test('admin health endpoint returns detailed diagnostic JSON for platform admin', function (): void {
    $admin = adminUser();

    Redis::sadd('workers:active', 'worker-test-diag');
    Redis::setex('worker:heartbeat:worker-test-diag', 45, (string) json_encode([
        'id' => 'worker-test-diag',
        'hostname' => 'diag-node-1',
        'timestamp' => time(),
    ], JSON_THROW_ON_ERROR));

    $response = $this->actingAs($admin)->getJson('/api/v1/admin/health');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'timestamp',
            'components' => [
                'database' => ['status', 'latency_ms'],
                'redis' => ['status', 'latency_ms'],
                'minio' => ['status', 'buckets', 'latency_ms'],
                'rabbitmq' => ['status', 'queue_depth', 'consumers', 'latency_ms'],
                'workers' => ['status', 'active_count', 'instances'],
            ],
        ]);

    $data = $response->json();
    expect($data['components']['minio']['buckets'])
        ->toContain('videos-original')
        ->toContain('videos-processed')
        ->toContain('thumbnails')
        ->and($data['components']['workers']['active_count'])->toBeGreaterThanOrEqual(1);
});

test('worker registry prunes expired workers and reports active count', function (): void {
    Redis::sadd('workers:active', 'active-worker');
    Redis::setex('worker:heartbeat:active-worker', 45, (string) json_encode([
        'id' => 'active-worker',
        'hostname' => 'host-1',
        'timestamp' => time(),
    ], JSON_THROW_ON_ERROR));

    Redis::sadd('workers:active', 'dead-worker');
    Redis::del('worker:heartbeat:dead-worker');

    /** @var HealthCheckServiceInterface $service */
    $service = app(HealthCheckServiceInterface::class);
    $report = $service->checkDiagnostics();

    expect(Redis::sismember('workers:active', 'dead-worker'))->toBeFalsy()
        ->and(Redis::sismember('workers:active', 'active-worker'))->toBeTruthy()
        ->and($report->components['workers']['active_count'])->toBe(1)
        ->and($report->components['workers']['instances'][0]['id'])->toBe('active-worker');
});

test('shallow liveness caches auxiliary checks and falls back safely if cache throws', function (): void {
    Redis::sadd('workers:active', 'worker-test-1');
    Redis::setex('worker:heartbeat:worker-test-1', 45, (string) json_encode([
        'id' => 'worker-test-1',
        'hostname' => 'worker-node-1',
        'timestamp' => time(),
    ], JSON_THROW_ON_ERROR));

    // First call populates cache
    $response = $this->get('/health');
    $response->assertOk();

    expect(Illuminate\Support\Facades\Cache::has('health:liveness:auxiliary'))->toBeTrue();

    // Verify cache fallback when Cache facade throws an exception
    Illuminate\Support\Facades\Cache::shouldReceive('remember')
        ->once()
        ->with('health:liveness:auxiliary', 10, Mockery::any())
        ->andThrow(new RuntimeException('Cache cluster down'));

    $service = app(HealthCheckServiceInterface::class);
    $liveness = $service->checkLiveness();

    // DB and Redis are ok, so liveness must not fail
    expect($liveness['status'])->toBeIn(['ok', 'degraded'])
        ->and($liveness['database'])->toBe('ok')
        ->and($liveness['redis'])->toBe('ok');
});
