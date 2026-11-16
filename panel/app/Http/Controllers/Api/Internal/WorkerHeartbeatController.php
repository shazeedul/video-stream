<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

final class WorkerHeartbeatController extends Controller
{
    /**
     * Handle incoming worker heartbeat registration and shutdown signal.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'worker_id' => ['required', 'string'],
            'hostname' => ['required', 'string'],
            'status' => ['required', 'string', 'in:active,stopped'],
        ]);

        $workerId = (string) $validated['worker_id'];
        $hostname = (string) $validated['hostname'];
        $status = (string) $validated['status'];

        if ($status === 'active') {
            Redis::setex(
                "worker:heartbeat:{$workerId}",
                45,
                (string) json_encode([
                    'id' => $workerId,
                    'hostname' => $hostname,
                    'timestamp' => time(),
                ], JSON_THROW_ON_ERROR),
            );
            Redis::sadd('workers:active', $workerId);
        } elseif ($status === 'stopped') {
            Redis::srem('workers:active', $workerId);
            Redis::del("worker:heartbeat:{$workerId}");
        }

        return response()->json(['status' => 'recorded'], 200);
    }
}
