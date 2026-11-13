<?php

declare(strict_types=1);

namespace App\Domain\Processing\Models;

use App\Domain\Processing\Enums\OutboxStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'event_type',
    'aggregate_type',
    'aggregate_id',
    'payload',
    'status',
    'attempts',
    'error_message',
    'published_at',
])]
final class MediaOutboxMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'status',
        'attempts',
        'error_message',
        'published_at',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => OutboxStatus::PENDING->value,
        'attempts' => 0,
    ];

    /**
     * Scope query to pending outbox messages.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', OutboxStatus::PENDING);
    }

    /**
     * Scope query to published outbox messages.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', OutboxStatus::PUBLISHED);
    }

    /**
     * Scope query to failed outbox messages.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', OutboxStatus::FAILED);
    }

    /**
     * Mark the outbox message as successfully published.
     */
    public function markPublished(): void
    {
        $this->forceFill([
            'status' => OutboxStatus::PUBLISHED,
            'published_at' => now(),
            'error_message' => null,
        ])->save();
    }

    /**
     * Mark the outbox message as failed with an error message and increment attempts.
     */
    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => OutboxStatus::FAILED,
            'attempts' => $this->attempts + 1,
            'error_message' => $error,
        ])->save();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OutboxStatus::class,
            'payload' => 'array',
            'published_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }
}
