<?php

declare(strict_types=1);

use App\Domain\Playback\Contracts\PlaybackTokenServiceInterface;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\Video;
use App\Enums\TenantRole;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\User;

beforeEach(function (): void {
    config()->set('inertia.testing.ensure_pages_exist', false);
    $this->withoutVite();
});

test('public ready video renders embed page successfully for guest', function (): void {
    $tenant = Tenant::factory()->create();
    $video = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'visibility' => VideoVisibility::PUBLIC,
        'status' => VideoStatus::READY,
    ]);

    $response = $this->get(route('videos.embed', $video));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('embed/video')
            ->where('video.id', $video->id)
            ->has('playback_url')
        );

    expect($response->headers->get('Content-Security-Policy'))->toContain('frame-ancestors *')
        ->and($response->headers->has('X-Frame-Options'))->toBeFalse();
});

test('unlisted ready video renders embed page successfully for guest', function (): void {
    $tenant = Tenant::factory()->create();
    $video = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'visibility' => VideoVisibility::UNLISTED,
        'status' => VideoStatus::READY,
    ]);

    $response = $this->get(route('videos.embed', $video));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('embed/video')
            ->where('video.id', $video->id)
            ->has('playback_url')
        );
});

test('private ready video returns 403 Forbidden for unauthenticated guest without token', function (): void {
    $tenant = Tenant::factory()->create();
    $video = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'visibility' => VideoVisibility::PRIVATE,
        'status' => VideoStatus::READY,
    ]);

    $response = $this->get(route('videos.embed', $video));

    $response->assertForbidden();
});

test('private ready video with valid HMAC token parameter returns 200 OK for guest', function (): void {
    $tenant = Tenant::factory()->create();
    $video = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'visibility' => VideoVisibility::PRIVATE,
        'status' => VideoStatus::READY,
    ]);

    $tokenService = app(PlaybackTokenServiceInterface::class);
    $expires = time() + 3600;
    $token = $tokenService->generateToken((string) $video->id, $expires);

    $response = $this->get(route('videos.embed', [
        'video' => $video,
        'token' => $token,
        'expires' => $expires,
    ]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('embed/video')
            ->where('video.id', $video->id)
            ->has('playback_url')
        );
});

test('private ready video returns 403 Forbidden for guest with invalid or expired token', function (): void {
    $tenant = Tenant::factory()->create();
    $video = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'visibility' => VideoVisibility::PRIVATE,
        'status' => VideoStatus::READY,
    ]);

    $tokenService = app(PlaybackTokenServiceInterface::class);
    $expiredTime = time() - 3600;
    $token = $tokenService->generateToken((string) $video->id, $expiredTime);

    $response = $this->get(route('videos.embed', [
        'video' => $video,
        'token' => $token,
        'expires' => $expiredTime,
    ]));

    $response->assertForbidden();
});

test('private ready video returns 200 OK for authenticated tenant member', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $video = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'visibility' => VideoVisibility::PRIVATE,
        'status' => VideoStatus::READY,
    ]);

    $response = $this->actingAs($user)->get(route('videos.embed', $video));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('embed/video')
            ->where('video.id', $video->id)
            ->has('playback_url')
        );
});

test('private ready video returns 403 Forbidden for authenticated user from another tenant', function (): void {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant1->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $video = Video::factory()->create([
        'tenant_id' => $tenant2->id,
        'visibility' => VideoVisibility::PRIVATE,
        'status' => VideoStatus::READY,
    ]);

    $response = $this->actingAs($user)->get(route('videos.embed', $video));

    $response->assertForbidden();
});

test('non-ready video returns 404 Not Found', function (): void {
    $tenant = Tenant::factory()->create();
    $video = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'visibility' => VideoVisibility::PUBLIC,
        'status' => VideoStatus::DRAFT,
    ]);

    $response = $this->get(route('videos.embed', $video));

    $response->assertNotFound();
});

test('verifies X-Frame-Options is omitted or relaxed and Content-Security-Policy allows frame-ancestors *', function (): void {
    $tenant = Tenant::factory()->create();
    $video = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'visibility' => VideoVisibility::PUBLIC,
        'status' => VideoStatus::READY,
    ]);

    $response = $this->get(route('videos.embed', $video));

    $response->assertOk();
    expect($response->headers->has('X-Frame-Options'))->toBeFalse()
        ->and($response->headers->get('Content-Security-Policy'))->toContain('frame-ancestors *');
});

test('passes autoplay, muted, and loop query parameters to inertia page', function (): void {
    $tenant = Tenant::factory()->create();
    $video = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'visibility' => VideoVisibility::PUBLIC,
        'status' => VideoStatus::READY,
    ]);

    $response = $this->get(route('videos.embed', [
        'video' => $video,
        'autoplay' => 1,
        'muted' => 1,
        'loop' => 1,
    ]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('embed/video')
            ->where('video.id', $video->id)
            ->where('autoplay', true)
            ->where('muted', true)
            ->where('loop', true)
        );
});
