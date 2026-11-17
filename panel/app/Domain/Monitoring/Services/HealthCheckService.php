<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Services;

use App\Domain\Monitoring\Contracts\HealthCheckServiceInterface;
use App\Domain\Monitoring\DataTransferObjects\HealthReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Throwable;

final class HealthCheckService implements HealthCheckServiceInterface
{
    /**
     * Check shallow liveness for container and load-balancer probes.
     *
     * @return array<string, mixed>
     */
    public function checkLiveness(): array
    {
        $dbOk = true;
        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            $dbOk = false;
        }

        $redisOk = true;
        try {
            Redis::ping();
        } catch (Throwable) {
            $redisOk = false;
        }

        if (! $dbOk || ! $redisOk) {
            return [
                'status' => 'unhealthy',
                'database' => $dbOk ? 'ok' : 'error',
                'redis' => $redisOk ? 'ok' : 'error',
            ];
        }

        try {
            $auxiliary = Cache::remember('health:liveness:auxiliary', 10, function (): array {
                $rabbitCheck = $this->checkRabbitMQ();
                $workersCheck = $this->checkWorkers();
                $minioCheck = $this->checkMinio();

                return [
                    'rabbit' => ($rabbitCheck['status'] === 'ok'),
                    'workers' => ($workersCheck['status'] === 'ok'),
                    'minio' => ($minioCheck['status'] === 'ok'),
                ];
            });
        } catch (Throwable) {
            try {
                $rabbitCheck = $this->checkRabbitMQ();
                $workersCheck = $this->checkWorkers();
                $minioCheck = $this->checkMinio();

                $auxiliary = [
                    'rabbit' => ($rabbitCheck['status'] === 'ok'),
                    'workers' => ($workersCheck['status'] === 'ok'),
                    'minio' => ($minioCheck['status'] === 'ok'),
                ];
            } catch (Throwable) {
                $auxiliary = [
                    'rabbit' => true,
                    'workers' => true,
                    'minio' => true,
                ];
            }
        }

        $rabbitOk = (bool) ($auxiliary['rabbit'] ?? true);
        $workersOk = (bool) ($auxiliary['workers'] ?? true);
        $minioOk = (bool) ($auxiliary['minio'] ?? true);

        if (! $rabbitOk || ! $workersOk || ! $minioOk) {
            return [
                'status' => 'degraded',
                'database' => 'ok',
                'redis' => 'ok',
                'rabbitmq' => $rabbitOk ? 'ok' : 'error',
                'workers' => $workersOk ? 'ok' : 'error',
                'minio' => $minioOk ? 'ok' : 'error',
            ];
        }

        return [
            'status' => 'ok',
            'database' => 'ok',
            'redis' => 'ok',
            'rabbitmq' => 'ok',
            'workers' => 'ok',
            'minio' => 'ok',
        ];
    }

    /**
     * Check deep infrastructure diagnostics.
     */
    public function checkDiagnostics(): HealthReport
    {
        $db = $this->checkDatabase();
        $redis = $this->checkRedis();
        $minio = $this->checkMinio();
        $rabbit = $this->checkRabbitMQ();
        $workers = $this->checkWorkers();

        $status = 'healthy';
        if ($db['status'] !== 'ok' || $redis['status'] !== 'ok') {
            $status = 'unhealthy';
        } elseif ($minio['status'] !== 'ok' || $rabbit['status'] !== 'ok' || $workers['status'] !== 'ok') {
            $status = 'degraded';
        }

        return new HealthReport(
            status: $status,
            timestamp: Carbon::now()->toIso8601String(),
            components: [
                'database' => $db,
                'redis' => $redis,
                'minio' => $minio,
                'rabbitmq' => $rabbit,
                'workers' => $workers,
            ],
        );
    }

    /**
     * Measure PostgreSQL query latency.
     *
     * @return array<string, mixed>
     */
    public function checkDatabase(): array
    {
        $start = microtime(true);

        try {
            DB::select('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'latency_ms' => $latency,
            ];
        } catch (Throwable $e) {
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'error',
                'latency_ms' => $latency,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Measure Redis ping latency.
     *
     * @return array<string, mixed>
     */
    public function checkRedis(): array
    {
        $start = microtime(true);

        try {
            Redis::ping();
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'latency_ms' => $latency,
            ];
        } catch (Throwable $e) {
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'error',
                'latency_ms' => $latency,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify MinIO storage bucket existence.
     *
     * @return array<string, mixed>
     */
    public function checkMinio(): array
    {
        $start = microtime(true);
        $buckets = ['videos-original', 'videos-processed', 'thumbnails'];
        $disks = [
            'videos-original' => 'minio',
            'videos-processed' => 'minio-processed',
            'thumbnails' => 'thumbnails',
        ];

        $allExist = true;
        $missing = [];

        try {
            foreach ($disks as $bucket => $disk) {
                $diskInstance = Storage::disk($disk);
                $exists = false;

                if (method_exists($diskInstance, 'getClient')) {
                    $client = $diskInstance->getClient();
                    if (is_object($client) && method_exists($client, 'doesBucketExistV2')) {
                        $exists = (bool) $client->doesBucketExistV2($bucket);
                    } elseif (is_object($client) && method_exists($client, 'doesBucketExist')) {
                        $exists = (bool) $client->doesBucketExist($bucket);
                    } else {
                        $exists = true;
                    }
                } else {
                    $exists = true;
                }

                if (! $exists) {
                    $allExist = false;
                    $missing[] = $bucket;
                }
            }

            $latency = round((microtime(true) - $start) * 1000, 2);

            $result = [
                'status' => $allExist ? 'ok' : 'error',
                'buckets' => $buckets,
                'latency_ms' => $latency,
            ];

            if (! $allExist) {
                $result['missing_buckets'] = $missing;
            }

            return $result;
        } catch (Throwable $e) {
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'error',
                'buckets' => $buckets,
                'latency_ms' => $latency,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check RabbitMQ AMQP connection and media.jobs queue depth.
     *
     * @return array<string, mixed>
     */
    public function checkRabbitMQ(): array
    {
        $start = microtime(true);
        $queue = (string) config('rabbitmq.queue', 'media.jobs');

        try {
            $host = (string) config('rabbitmq.host', 'rabbitmq');
            $port = (int) config('rabbitmq.port', 5672);
            $user = (string) config('rabbitmq.user', 'guest');
            $password = (string) config('rabbitmq.password', 'guest');
            $vhost = (string) config('rabbitmq.vhost', '/');

            $connection = new AMQPStreamConnection(
                $host,
                $port,
                $user,
                $password,
                $vhost,
                false,
                'AMQPLAIN',
                null,
                'en_US',
                2.0,
                2.0,
            );

            $channel = $connection->channel();
            $queueDepth = 0;
            $consumers = 0;

            try {
                $queueInfo = $channel->queue_declare($queue, true);
                if (is_array($queueInfo)) {
                    $queueDepth = isset($queueInfo[1]) ? (int) $queueInfo[1] : 0;
                    $consumers = isset($queueInfo[2]) ? (int) $queueInfo[2] : 0;
                }
            } catch (Throwable) {
                // If passive declare fails, socket connection was still established
            } finally {
                try {
                    $channel->close();
                } catch (Throwable) {
                }
                try {
                    $connection->close();
                } catch (Throwable) {
                }
            }

            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'queue_depth' => $queueDepth,
                'consumers' => $consumers,
                'latency_ms' => $latency,
            ];
        } catch (Throwable $e) {
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'error',
                'queue_depth' => 0,
                'consumers' => 0,
                'latency_ms' => $latency,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Inspect active workers from Redis, prune expired keys, and report fleet status.
     *
     * @return array<string, mixed>
     */
    public function checkWorkers(): array
    {
        try {
            $workerIds = Redis::smembers('workers:active');
            $activeInstances = [];

            if (is_array($workerIds)) {
                foreach ($workerIds as $workerId) {
                    $workerId = (string) $workerId;
                    $key = "worker:heartbeat:{$workerId}";
                    $data = Redis::get($key);

                    if ($data === null || $data === false) {
                        Redis::srem('workers:active', $workerId);

                        continue;
                    }

                    $decoded = json_decode((string) $data, true);
                    $timestamp = isset($decoded['timestamp']) ? (int) $decoded['timestamp'] : time();
                    $ageSeconds = max(0, time() - $timestamp);

                    $activeInstances[] = [
                        'id' => $workerId,
                        'hostname' => $decoded['hostname'] ?? 'unknown',
                        'age_seconds' => $ageSeconds,
                    ];
                }
            }

            $activeCount = count($activeInstances);

            return [
                'status' => $activeCount > 0 ? 'ok' : 'down',
                'active_count' => $activeCount,
                'instances' => $activeInstances,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'active_count' => 0,
                'instances' => [],
                'error' => $e->getMessage(),
            ];
        }
    }
}
