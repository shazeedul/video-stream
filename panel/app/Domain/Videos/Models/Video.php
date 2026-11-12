<?php

declare(strict_types=1);

namespace App\Domain\Videos\Models;

use App\Domain\Tenants\Traits\BelongsToTenant;
use App\Domain\Uploads\Models\UploadSession;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\User;
use Database\Factories\VideoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'user_id',
    'title',
    'description',
    'visibility',
    'status',
    'duration_seconds',
    'thumbnail_path',
    'metadata',
])]
final class Video extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'title',
        'description',
        'visibility',
        'status',
        'duration_seconds',
        'thumbnail_path',
        'metadata',
    ];

    /**
     * The user who created the video.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Assets associated with this video.
     */
    public function assets(): HasMany
    {
        return $this->hasMany(VideoAsset::class, 'video_id');
    }

    /**
     * Upload sessions associated with this video.
     */
    public function uploadSessions(): HasMany
    {
        return $this->hasMany(UploadSession::class, 'video_id');
    }

    /**
     * Scope search query across title and description.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || mb_trim($term) === '') {
            return $query;
        }

        $term = mb_trim($term);

        return $query->where(function (Builder $builder) use ($term): void {
            $builder->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($term).'%'])
                ->orWhereRaw('LOWER(description) LIKE ?', ['%'.mb_strtolower($term).'%']);
        });
    }

    /**
     * Scope query to videos with the specified status.
     */
    public function scopeOfStatus(Builder $query, VideoStatus|string $status): Builder
    {
        $statusValue = $status instanceof VideoStatus ? $status->value : $status;

        return $query->where('status', $statusValue);
    }

    /**
     * Scope query to videos with the specified visibility.
     */
    public function scopeOfVisibility(Builder $query, VideoVisibility|string $visibility): Builder
    {
        $visibilityValue = $visibility instanceof VideoVisibility ? $visibility->value : $visibility;

        return $query->where('visibility', $visibilityValue);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): VideoFactory
    {
        return VideoFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VideoStatus::class,
            'visibility' => VideoVisibility::class,
            'metadata' => 'array',
            'duration_seconds' => 'integer',
        ];
    }
}
