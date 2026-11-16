<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\DataTransferObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final class HealthReport implements Arrayable, JsonSerializable
{
    /**
     * @param  array<string, mixed>  $components
     */
    public function __construct(
        public readonly string $status,
        public readonly string $timestamp,
        public readonly array $components,
    ) {}

    /**
     * @return array{status: string, timestamp: string, components: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'timestamp' => $this->timestamp,
            'components' => $this->components,
        ];
    }

    /**
     * @return array{status: string, timestamp: string, components: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
