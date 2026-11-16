<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Internal;

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

final class WorkerHeartbeatTest extends TestCase
{
    private string $secret = 'test-worker-internal-secret-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.internal.secret' => $this->secret]);
        Redis::connection()->flushdb();
    }

    protected function tearDown(): void
    {
        Redis::connection()->flushdb();
        parent::tearDown();
    }

    public function test_valid_heartbeat_registers_worker_in_redis_with_ttl_and_returns_200(): void
    {
        $workerId = 'worker-node-1-abc12345';
        $hostname = 'node-1.wiz.internal';

        $response = $this->withHeaders(['X-Internal-Secret' => $this->secret])
            ->postJson('/api/v1/internal/worker/heartbeat', [
                'worker_id' => $workerId,
                'hostname' => $hostname,
                'status' => 'active',
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'recorded',
            ]);

        // Verify Redis key storage and TTL
        $redisKey = "worker:heartbeat:{$workerId}";
        $this->assertSame(1, (int) Redis::exists($redisKey));

        $ttl = (int) Redis::ttl($redisKey);
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(45, $ttl);

        $payload = json_decode((string) Redis::get($redisKey), true);
        $this->assertIsArray($payload);
        $this->assertSame($workerId, $payload['id']);
        $this->assertSame($hostname, $payload['hostname']);
        $this->assertIsInt($payload['timestamp']);

        // Verify Set membership
        $this->assertTrue((bool) Redis::sismember('workers:active', $workerId));
    }

    public function test_missing_or_invalid_secret_is_rejected(): void
    {
        // Missing secret
        $response = $this->postJson('/api/v1/internal/worker/heartbeat', [
            'worker_id' => 'worker-test-1',
            'hostname' => 'node-1',
            'status' => 'active',
        ]);
        $response->assertUnauthorized();

        // Invalid secret
        $response = $this->withHeaders(['X-Internal-Secret' => 'invalid-secret'])
            ->postJson('/api/v1/internal/worker/heartbeat', [
                'worker_id' => 'worker-test-1',
                'hostname' => 'node-1',
                'status' => 'active',
            ]);
        $response->assertUnauthorized();
    }

    public function test_stopped_status_removes_worker_from_active_set_and_deletes_key(): void
    {
        $workerId = 'worker-node-1-abc12345';
        $hostname = 'node-1.wiz.internal';

        // Register active worker first
        $this->withHeaders(['X-Internal-Secret' => $this->secret])
            ->postJson('/api/v1/internal/worker/heartbeat', [
                'worker_id' => $workerId,
                'hostname' => $hostname,
                'status' => 'active',
            ])->assertOk();

        $redisKey = "worker:heartbeat:{$workerId}";
        $this->assertSame(1, (int) Redis::exists($redisKey));
        $this->assertTrue((bool) Redis::sismember('workers:active', $workerId));

        // Send stopped heartbeat
        $response = $this->withHeaders(['X-Internal-Secret' => $this->secret])
            ->postJson('/api/v1/internal/worker/heartbeat', [
                'worker_id' => $workerId,
                'hostname' => $hostname,
                'status' => 'stopped',
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'recorded',
            ]);

        // Key should be deleted and removed from active set
        $this->assertSame(0, (int) Redis::exists($redisKey));
        $this->assertFalse((bool) Redis::sismember('workers:active', $workerId));
    }

    public function test_it_validates_required_fields_and_allowed_status(): void
    {
        $response = $this->withHeaders(['X-Internal-Secret' => $this->secret])
            ->postJson('/api/v1/internal/worker/heartbeat', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['worker_id', 'hostname', 'status']);

        $invalidStatusResponse = $this->withHeaders(['X-Internal-Secret' => $this->secret])
            ->postJson('/api/v1/internal/worker/heartbeat', [
                'worker_id' => 'worker-test',
                'hostname' => 'host-test',
                'status' => 'unknown-status',
            ]);

        $invalidStatusResponse->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }
}
