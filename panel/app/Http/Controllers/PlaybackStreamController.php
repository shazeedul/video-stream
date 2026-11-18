<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Playback\Contracts\PlaybackTokenServiceInterface;
use App\Domain\Usage\Contracts\TenantUsageServiceInterface;
use App\Domain\Videos\Models\Video;
use App\Enums\VideoStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class PlaybackStreamController extends Controller
{
    /**
     * Direct playback streaming fallback for HLS playlists and video segments.
     */
    public function __invoke(
        Request $request,
        string $videoId,
        string $asset,
        PlaybackTokenServiceInterface $playbackTokenService,
        TenantUsageServiceInterface $usageService,
    ): Response {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        /** @var Video|null $video */
        $video = Cache::remember("playback:video:{$videoId}", 60, function () use ($videoId) {
            return Video::withoutTenantScope()->with('tenant')->find($videoId);
        });

        if ($video === null || $video->status !== VideoStatus::READY) {
            return response('Video not ready or not found', 404);
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

        if (! $authorized) {
            return response('Playback unauthorized', 403);
        }

        // Record usage metering
        try {
            $originalUri = $request->getRequestUri();
            if (str_contains($asset, 'master.m3u8')) {
                $clientIdentifier = (string) ($token ?? $request->ip() ?? 'unknown');
                $usageService->recordPlaybackView((string) $video->tenant_id, (string) $video->id, $clientIdentifier);
            }

            if (str_ends_with($asset, '.ts') || str_contains($asset, '.ts?')) {
                $usageService->recordPlaybackEgress(
                    (string) $video->tenant_id,
                    TenantUsageServiceInterface::DEFAULT_SEGMENT_ESTIMATE_BYTES
                );
            }
        } catch (Throwable $e) {
            Log::warning('Playback usage metering failed in direct stream: '.$e->getMessage());
        }

        $disk = Storage::disk('minio-processed');
        $storagePath = "tenants/{$video->tenant_id}/videos/{$video->id}/hls/{$asset}";

        $mimeType = str_ends_with($asset, '.m3u8')
            ? 'application/vnd.apple.mpegurl'
            : (str_ends_with($asset, '.ts') ? 'video/MP2T' : ($disk->mimeType($storagePath) ?? 'application/octet-stream'));

        if ($request->isMethod('HEAD')) {
            if (! $disk->exists($storagePath)) {
                return response('Asset not found', 404);
            }

            $size = $disk->size($storagePath);

            return response('', 200, [
                'Content-Type' => $mimeType,
                'Content-Length' => (string) $size,
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
                'Access-Control-Allow-Headers' => '*',
                'Cache-Control' => str_ends_with($asset, '.m3u8') ? 'no-cache' : 'public, max-age=86400',
            ]);
        }

        try {
            $stream = $disk->readStream($storagePath);
        } catch (Throwable) {
            return response('Asset not found', 404);
        }

        if ($stream === null) {
            return response('Asset not found', 404);
        }

        $size = $disk->size($storagePath);

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) $size,
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => '*',
            'Cache-Control' => str_ends_with($asset, '.m3u8') ? 'no-cache' : 'public, max-age=86400',
        ];

        return new StreamedResponse(function () use ($stream): void {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers);
    }
}
