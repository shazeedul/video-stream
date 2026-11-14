<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Playback\Contracts\PlaybackTokenServiceInterface;
use App\Domain\Videos\Models\Video;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class EmbedController extends Controller
{
    public function __construct(
        private readonly PlaybackTokenServiceInterface $playbackTokenService,
    ) {}

    public function show(Request $request, Video $video): Response
    {
        // 1. Status Check
        if ($video->status !== VideoStatus::READY) {
            abort(404, 'Video is not ready for playback.');
        }

        // 2. Access Control / Visibility Check
        $token = $request->query('token');
        $expires = $request->integer('expires');

        $isTokenValid = false;
        if (! empty($token) && $expires > 0) {
            $isTokenValid = $this->playbackTokenService->validateToken((string) $video->id, (string) $token, $expires);
        }

        if (! $isTokenValid) {
            if ($video->visibility === VideoVisibility::PRIVATE) {
                $user = $request->user();
                if ($user === null || $user->cannot('view', $video)) {
                    abort(403, 'Unauthorized');
                }
            }
        }

        // 3. Generate Playback URL
        $playbackUrl = $this->playbackTokenService->getPlaybackUrl($video);

        // 4. Query params for playback options
        $autoplay = $request->boolean('autoplay', false);
        $muted = $request->boolean('muted', false);
        $loop = $request->boolean('loop', false);

        // 5. Inertia Response & Framing Headers
        $response = Inertia::render('embed/video', [
            'video' => $video,
            'playback_url' => $playbackUrl,
            'autoplay' => $autoplay,
            'muted' => $muted,
            'loop' => $loop,
        ])->toResponse($request);

        $response->headers->remove('X-Frame-Options');
        $response->headers->set('Content-Security-Policy', 'frame-ancestors *');

        return $response;
    }
}
