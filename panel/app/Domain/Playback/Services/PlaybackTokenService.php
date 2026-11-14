<?php

declare(strict_types=1);

namespace App\Domain\Playback\Services;

use App\Domain\Playback\Contracts\PlaybackTokenServiceInterface;
use App\Domain\Videos\Models\Video;

final class PlaybackTokenService implements PlaybackTokenServiceInterface
{
    public function __construct(
        private ?string $key = null,
        private ?string $baseUrl = null,
    ) {}

    public function generateToken(string $videoId, int $expiresAt): string
    {
        $key = $this->resolveKey();
        $payload = "{$videoId}|{$expiresAt}";
        $rawSignature = hash_hmac('sha256', $payload, $key, true);

        return mb_rtrim(strtr(base64_encode($rawSignature), '+/', '-_'), '=');
    }

    public function validateToken(string $videoId, string $token, int $expiresAt): bool
    {
        if ($expiresAt < time()) {
            return false;
        }

        $expectedToken = $this->generateToken($videoId, $expiresAt);

        return hash_equals($expectedToken, $token);
    }

    public function getPlaybackUrl(Video $video, ?int $ttlSeconds = 86400): string
    {
        $expiresAt = time() + ($ttlSeconds ?? 86400);
        $videoId = (string) $video->id;
        $token = $this->generateToken($videoId, $expiresAt);
        $baseUrl = mb_rtrim($this->resolveBaseUrl(), '/');

        return "{$baseUrl}/playback/{$videoId}/master.m3u8?token={$token}&expires={$expiresAt}";
    }

    private function resolveKey(): string
    {
        return $this->key ?? (string) config('app.key', '');
    }

    private function resolveBaseUrl(): string
    {
        return $this->baseUrl ?? (string) config('app.url', 'http://localhost:8088');
    }
}
