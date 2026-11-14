<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Internal;

use App\Domain\Videos\Models\Video;
use App\Domain\Videos\Services\VideoAssetRegistrationService;
use App\Enums\VideoStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\ProcessingCallbackRequest;
use Illuminate\Http\JsonResponse;

final class ProcessingCallbackController extends Controller
{
    /**
     * Handle the processing callback from the background worker.
     */
    public function __invoke(
        ProcessingCallbackRequest $request,
        VideoAssetRegistrationService $assetRegistrationService,
    ): JsonResponse {
        $validated = $request->validated();

        /** @var Video $video */
        $video = Video::withoutTenantScope()->findOrFail($validated['video_id']);

        $status = (string) $validated['status'];
        $metadata = is_array($video->metadata) ? $video->metadata : [];

        if (! empty($validated['metadata']) && is_array($validated['metadata'])) {
            $metadata = array_merge($metadata, $validated['metadata']);
        }

        if ($status === 'probing') {
            $video->status = VideoStatus::PROBING;
        } elseif ($status === 'probed') {
            if (isset($validated['duration_seconds'])) {
                $video->duration_seconds = (int) $validated['duration_seconds'];
            }
        } elseif ($status === 'transcoding') {
            $video->status = VideoStatus::TRANSCODING;
        } elseif ($status === 'packaging') {
            $video->status = VideoStatus::PACKAGING;
        } elseif ($status === 'ready') {
            $video->metadata = $metadata;
            $video = $assetRegistrationService->registerProcessedAssets(
                $video,
                $validated['assets'] ?? [],
                $validated['thumbnail_key'] ?? null,
                isset($validated['duration_seconds']) ? (int) $validated['duration_seconds'] : null,
            );

            return response()->json([
                'success' => true,
                'video_id' => $video->id,
            ]);
        } elseif ($status === 'failed') {
            $video->status = VideoStatus::FAILED;
            if (! empty($validated['error'])) {
                $metadata['error'] = (string) $validated['error'];
            }
        }

        $video->metadata = $metadata;
        $video->save();

        return response()->json([
            'success' => true,
            'video_id' => $video->id,
        ]);
    }
}
