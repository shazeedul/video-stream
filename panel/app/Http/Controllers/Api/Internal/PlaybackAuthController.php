<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Internal;

use App\Domain\Playback\Contracts\PlaybackTokenServiceInterface;
use App\Domain\Videos\Models\Video;
use App\Enums\VideoStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PlaybackAuthController extends Controller
{
    /**
     * Authorize playback requests for Nginx auth_request subrequests.
     */
    public function __invoke(
        Request $request,
        PlaybackTokenServiceInterface $playbackTokenService,
    ): Response {
        $videoId = (string) ($request->query('video_id') ?? $request->header('X-Video-Id') ?? '');

        if ($videoId === '') {
            return response('Missing video identifier', 400);
        }

        /** @var Video|null $video */
        $video = Video::withoutTenantScope()->find($videoId);

        if ($video === null || $video->status !== VideoStatus::READY) {
            return response('Video not ready or not found', 403);
        }

        $token = $request->query('token');
        $expires = $request->query('expires') !== null ? (int) $request->query('expires') : null;

        if ($token !== null && $expires !== null && $playbackTokenService->validateToken((string) $video->id, (string) $token, $expires)) {
            return response('', 200)->header('X-Tenant-Id', (string) $video->tenant_id);
        }

        /** @var User|null $user */
        $user = $request->user() ?? auth('sanctum')->user();

        if ($user instanceof User && $video->tenant !== null && $user->isTenantMember($video->tenant)) {
            return response('', 200)->header('X-Tenant-Id', (string) $video->tenant_id);
        }

        return response('Playback unauthorized', 403);
    }
}
