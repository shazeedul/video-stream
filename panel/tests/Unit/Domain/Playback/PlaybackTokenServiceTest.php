<?php

declare(strict_types=1);

use App\Domain\Playback\Contracts\PlaybackTokenServiceInterface;
use App\Domain\Playback\Services\PlaybackTokenService;
use App\Domain\Videos\Models\Video;
use Tests\TestCase;

uses(TestCase::class);

test('service can be resolved from container via interface', function () {
    $service = app(PlaybackTokenServiceInterface::class);

    expect($service)->toBeInstanceOf(PlaybackTokenService::class);
});

test('token generation produces non-empty url-safe string', function () {
    $service = new PlaybackTokenService();
    $videoId = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';
    $expiresAt = time() + 3600;

    $token = $service->generateToken($videoId, $expiresAt);

    expect($token)->toBeString()
        ->not->toBeEmpty()
        ->toMatch('/^[A-Za-z0-9_-]+$/');
});

test('valid token validates successfully with matching videoId and unexpired timestamp', function () {
    $service = new PlaybackTokenService();
    $videoId = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';
    $expiresAt = time() + 3600;

    $token = $service->generateToken($videoId, $expiresAt);

    expect($service->validateToken($videoId, $token, $expiresAt))->toBeTrue();
});

test('validation fails if timestamp is in the past', function () {
    $service = new PlaybackTokenService();
    $videoId = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';
    $expiredTimestamp = time() - 60;

    $token = $service->generateToken($videoId, $expiredTimestamp);

    expect($service->validateToken($videoId, $token, $expiredTimestamp))->toBeFalse();
});

test('validation fails if videoId is tampered with', function () {
    $service = new PlaybackTokenService();
    $videoId = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';
    $tamperedVideoId = '8a0cea3c-2a6c-3a9c-8acc-1a0c6a2cba5c';
    $expiresAt = time() + 3600;

    $token = $service->generateToken($videoId, $expiresAt);

    expect($service->validateToken($tamperedVideoId, $token, $expiresAt))->toBeFalse();
});

test('validation fails if signature token is modified', function () {
    $service = new PlaybackTokenService();
    $videoId = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';
    $expiresAt = time() + 3600;

    $token = $service->generateToken($videoId, $expiresAt);
    $tamperedToken = $token.'tampered';

    expect($service->validateToken($videoId, $tamperedToken, $expiresAt))->toBeFalse();
});

test('getPlaybackUrl builds expected playback url with default ttl', function () {
    $service = new PlaybackTokenService();
    $video = new Video();
    $video->id = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';

    $before = time() + 86400;
    $url = $service->getPlaybackUrl($video);
    $after = time() + 86400;

    $appUrl = mb_rtrim((string) config('app.url', 'http://localhost:8088'), '/');
    $expectedPrefix = "{$appUrl}/playback/{$video->id}/master.m3u8?";

    expect($url)->toStartWith($expectedPrefix);

    $parsed = parse_url($url);
    parse_str($parsed['query'] ?? '', $queryParams);

    expect($queryParams)->toHaveKeys(['token', 'expires']);
    expect((int) $queryParams['expires'])->toBeGreaterThanOrEqual($before);
    expect((int) $queryParams['expires'])->toBeLessThanOrEqual($after);

    $token = (string) $queryParams['token'];
    $expiresAt = (int) $queryParams['expires'];

    expect($service->validateToken((string) $video->id, $token, $expiresAt))->toBeTrue();
});

test('getPlaybackUrl respects custom ttlSeconds', function () {
    $service = new PlaybackTokenService();
    $video = new Video();
    $video->id = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';

    $ttl = 7200;
    $before = time() + $ttl;
    $url = $service->getPlaybackUrl($video, $ttl);
    $after = time() + $ttl;

    $parsed = parse_url($url);
    parse_str($parsed['query'] ?? '', $queryParams);

    expect($queryParams)->toHaveKeys(['token', 'expires']);
    expect((int) $queryParams['expires'])->toBeGreaterThanOrEqual($before);
    expect((int) $queryParams['expires'])->toBeLessThanOrEqual($after);

    $token = (string) $queryParams['token'];
    $expiresAt = (int) $queryParams['expires'];

    expect($service->validateToken((string) $video->id, $token, $expiresAt))->toBeTrue();
});

test('getPlaybackUrl handles trailing slash in base url', function () {
    $service = new PlaybackTokenService(baseUrl: 'http://example.com/');
    $video = new Video();
    $video->id = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';

    $url = $service->getPlaybackUrl($video);

    expect($url)->toStartWith('http://example.com/playback/9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d/master.m3u8');
});
