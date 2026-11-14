<?php

declare(strict_types=1);

namespace App\Domain\Videos\Services;

use App\Domain\Videos\Models\Video;
use App\Domain\Videos\Models\VideoAsset;
use App\Enums\AssetType;
use App\Enums\VideoStatus;
use App\Events\Videos\VideoStatusUpdated;
use Illuminate\Support\Facades\DB;

final class VideoAssetRegistrationService
{
    /**
     * Register processed assets and mark the video as ready.
     *
     * @param  array<int, array{type: string, disk: string, object_key: string, size_bytes: int, mime_type: string, metadata?: array<string, mixed>|null}>  $assets
     */
    public function registerProcessedAssets(
        Video $video,
        array $assets,
        ?string $thumbnailKey = null,
        ?int $durationSeconds = null,
    ): Video {
        return DB::transaction(function () use ($video, $assets, $thumbnailKey, $durationSeconds): Video {
            foreach ($assets as $asset) {
                $type = AssetType::tryFrom((string) $asset['type']) ?? (string) $asset['type'];

                VideoAsset::updateOrCreate(
                    [
                        'video_id' => $video->id,
                        'object_key' => (string) $asset['object_key'],
                    ],
                    [
                        'type' => $type,
                        'disk' => (string) $asset['disk'],
                        'size_bytes' => isset($asset['size_bytes']) ? (int) $asset['size_bytes'] : null,
                        'mime_type' => isset($asset['mime_type']) ? (string) $asset['mime_type'] : null,
                        'metadata' => $asset['metadata'] ?? null,
                    ]
                );
            }

            if ($thumbnailKey !== null) {
                $video->thumbnail_path = $thumbnailKey;
            }

            if ($durationSeconds !== null) {
                $video->duration_seconds = $durationSeconds;
            }

            $video->status = VideoStatus::READY;
            $video->save();

            VideoStatusUpdated::dispatch($video);

            return $video;
        });
    }
}
