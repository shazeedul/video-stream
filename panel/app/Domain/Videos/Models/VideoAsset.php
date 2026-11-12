<?php

declare(strict_types=1);

namespace App\Domain\Videos\Models;

use App\Enums\AssetType;
use Database\Factories\VideoAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'video_id',
    'type',
    'disk',
    'object_key',
    'size_bytes',
    'mime_type',
    'metadata',
])]
final class VideoAsset extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'video_id',
        'type',
        'disk',
        'object_key',
        'size_bytes',
        'mime_type',
        'metadata',
    ];

    /**
     * The video this asset belongs to.
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'video_id');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): VideoAssetFactory
    {
        return VideoAssetFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AssetType::class,
            'metadata' => 'array',
            'size_bytes' => 'integer',
        ];
    }
}
