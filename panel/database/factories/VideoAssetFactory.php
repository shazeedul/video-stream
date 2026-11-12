<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Videos\Models\Video;
use App\Domain\Videos\Models\VideoAsset;
use App\Enums\AssetType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VideoAsset>
 */
final class VideoAssetFactory extends Factory
{
    protected $model = VideoAsset::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'video_id' => Video::factory(),
            'type' => AssetType::ORIGINAL,
            'disk' => 'minio_originals',
            'object_key' => 'originals/'.(string) Str::uuid().'/source.mp4',
            'size_bytes' => fake()->numberBetween(1024 * 1024, 100 * 1024 * 1024),
            'mime_type' => 'video/mp4',
            'metadata' => [
                'codec' => 'h264',
                'bitrate' => 2500000,
            ],
        ];
    }

    /**
     * Indicate that the asset is a master video.
     */
    public function master(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => AssetType::MASTER,
            'disk' => 'minio_processed',
        ]);
    }

    /**
     * Indicate that the asset is an HLS playlist.
     */
    public function hlsPlaylist(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => AssetType::HLS_PLAYLIST,
            'disk' => 'minio_processed',
            'mime_type' => 'application/vnd.apple.mpegurl',
        ]);
    }

    /**
     * Indicate that the asset is a thumbnail image.
     */
    public function thumbnail(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => AssetType::THUMBNAIL,
            'disk' => 'minio_processed',
            'mime_type' => 'image/jpeg',
        ]);
    }
}
