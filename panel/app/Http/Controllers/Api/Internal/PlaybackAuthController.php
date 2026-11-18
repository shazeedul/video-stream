<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Internal;

use App\Domain\Playback\Contracts\PlaybackTokenServiceInterface;
use App\Domain\Usage\Contracts\TenantUsageServiceInterface;
use App\Domain\Videos\Models\Video;
use App\Enums\VideoStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PlaybackAuthController extends Controller
{
    /**
     * Authorize playback requests for Nginx auth_request subrequests.
     */
    public function __invoke(
        Request $request,
        PlaybackTokenServiceInterface $playbackTokenService,
        TenantUsageServiceInterface $usageService,
    ): Response {
        $originalUri = (string) $request->header('X-Original-URI', '');
        $headerVideoId = (string) $request->header('X-Video-Id', '');
        $queryVideoId = (string) $request->query('video_id', '');

        if ($headerVideoId !== '' && $queryVideoId !== '' && $headerVideoId !== $queryVideoId) {
            return response('Conflicting video identifiers', 400);
        }

        $videoId = $headerVideoId !== '' ? $headerVideoId : $queryVideoId;

        if ($videoId === '') {
            return response('Missing video identifier', 400);
        }

        /** @var Video|null $video */
        $video = Cache::remember("playback:video:{$videoId}", 60, function () use ($videoId) {
            return Video::withoutTenantScope()->with('tenant')->find($videoId);
        });

        if ($video === null || $video->status !== VideoStatus::READY) {
            return response('Video not ready or not found', 403);
        }

        $token = $request->query('token');
        $expires = $request->query('expires') !== null ? (int) $request->query('expires') : null;

        $authorized = false;

        if ($token !== null || $expires !== null) {
            if ($token !== null && $expires !== null && $playbackTokenService->validateToken((string) $video->id, (string) $token, $expires)) {
                $authorized = true;
            }
        } elseif ($video->visibility->isPublic() || $video->visibility->isUnlisted()) {
            $authorized = true;
        } else {
            /** @var User|null $user */
            $user = $request->user() ?? auth('sanctum')->user();
            if ($user instanceof User && $video->tenant !== null && $user->isTenantMember($video->tenant)) {
                $authorized = true;
            }
        }

        if ($authorized) {
            try {
                $this->recordUsage($usageService, $video, $originalUri, is_string($token) ? $token : null, $request);
            } catch (Throwable $e) {
                Log::warning('Playback usage metering failed: '.$e->getMessage());
            }

            return response('', 200)->header('X-Tenant-Id', (string) $video->tenant_id);
        }

        return response('Playback unauthorized', 403);
    }

    /**
     * Record playback view or egress usage upon successful authorization.
     */
    private function recordUsage(
        TenantUsageServiceInterface $usageService,
        Video $video,
        string $originalUri,
        ?string $token,
        Request $request,
    ): void {
        try {
            if ($originalUri === '') {
                return;
            }

            if (str_contains($originalUri, 'master.m3u8')) {
                $clientIdentifier = (string) ($token ?? $request->ip() ?? 'unknown');
                $usageService->recordPlaybackView((string) $video->tenant_id, (string) $video->id, $clientIdentifier);
            }

            if (str_ends_with($originalUri, '.ts') || str_contains($originalUri, '.ts?')) {
                $usageService->recordPlaybackEgress(
                    (string) $video->tenant_id,
                    TenantUsageServiceInterface::DEFAULT_SEGMENT_ESTIMATE_BYTES
                );
            }
        } catch (Throwable $e) {
            Log::warning('Playback usage metering failed: '.$e->getMessage());
        }
    }
}
