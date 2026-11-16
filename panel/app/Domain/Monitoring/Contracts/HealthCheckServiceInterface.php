<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Contracts;

use App\Domain\Monitoring\DataTransferObjects\HealthReport;

interface HealthCheckServiceInterface
{
    /**
     * Check shallow liveness for container and load-balancer probes.
     *
     * @return array<string, mixed>
     */
    public function checkLiveness(): array;

    /**
     * Check deep infrastructure diagnostics.
     */
    public function checkDiagnostics(): HealthReport;
}
