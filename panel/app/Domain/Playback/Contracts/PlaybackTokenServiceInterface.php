<?php

declare(strict_types=1);

namespace App\Domain\Playback\Contracts;

use App\Domain\Videos\Models\Video;

interface PlaybackTokenServiceInterface
{
    public function generateToken(string $videoId, int $expiresAt): string;

    public function validateToken(string $videoId, string $token, int $expiresAt): bool;

    public function getPlaybackUrl(Video $video, ?int $ttlSeconds = 86400): string;
}
