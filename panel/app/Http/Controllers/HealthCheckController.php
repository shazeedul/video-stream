<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Monitoring\Contracts\HealthCheckServiceInterface;
use Illuminate\Http\JsonResponse;

final class HealthCheckController extends Controller
{
    public function __construct(
        private readonly HealthCheckServiceInterface $healthCheckService,
    ) {}

    /**
     * Shallow liveness probe endpoint for load balancers and orchestrators.
     */
    public function liveness(): JsonResponse
    {
        $report = $this->healthCheckService->checkLiveness();
        $statusCode = ($report['status'] ?? '') === 'unhealthy' ? 503 : 200;

        return response()->json($report, $statusCode);
    }

    /**
     * Deep diagnostic probe endpoint for platform administrators.
     */
    public function diagnostics(): JsonResponse
    {
        $report = $this->healthCheckService->checkDiagnostics();

        return response()->json($report->toArray(), 200);
    }
}
