<?php

declare(strict_types=1);

namespace App\Domain\Uploads\Models;

use App\Domain\Tenants\Traits\BelongsToTenant;
use App\Domain\Videos\Models\Video;
use App\Enums\UploadSessionStatus;
use Database\Factories\UploadSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'video_id',
    'upload_id',
    'disk',
    'object_key',
    'part_size',
    'total_parts',
    'status',
    'expires_at',
])]
final class UploadSession extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'video_id',
        'upload_id',
        'disk',
        'object_key',
        'part_size',
        'total_parts',
        'status',
        'expires_at',
    ];

    /**
     * The video this upload session belongs to.
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'video_id');
    }

    /**
     * Check if the upload session has expired.
     */
    public function isExpired(): bool
    {
        return $this->status === UploadSessionStatus::EXPIRED
            || ($this->expires_at !== null && $this->expires_at->isPast());
    }

    /**
     * Determine whether new parts can still be uploaded to this session.
     */
    public function canAcceptParts(): bool
    {
        return $this->status === UploadSessionStatus::PENDING && ! $this->isExpired();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): UploadSessionFactory
    {
        return UploadSessionFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UploadSessionStatus::class,
            'expires_at' => 'datetime',
            'part_size' => 'integer',
            'total_parts' => 'integer',
        ];
    }
}
