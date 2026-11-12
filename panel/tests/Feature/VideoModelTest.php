<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Uploads\Models\UploadSession;
use App\Domain\Videos\Models\Video;
use App\Domain\Videos\Models\VideoAsset;
use App\Enums\AssetType;
use App\Enums\UploadSessionStatus;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\User;

test('belongs to tenant trait automatically scopes queries to active tenant and auto-fills tenant_id', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $user = User::factory()->create();

    app()->instance('current_tenant', $tenantA);

    $videoA = Video::factory()->create(['user_id' => $user->id, 'title' => 'Video in Tenant A']);
    expect($videoA->tenant_id)->toBe($tenantA->id);

    // Switch context to Tenant B
    app()->instance('current_tenant', $tenantB);
    $videoB = Video::factory()->create(['user_id' => $user->id, 'title' => 'Video in Tenant B']);

    // Queries should only see Tenant B videos
    expect(Video::count())->toBe(1)
        ->and(Video::first()->id)->toBe($videoB->id)
        ->and(Video::withoutTenantScope()->count())->toBe(2);
});

test('video model relationships, casts, and scopes work as expected', function (): void {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $video = Video::factory()->create([
        'status' => VideoStatus::READY,
        'visibility' => VideoVisibility::PUBLIC,
    ]);

    $asset = VideoAsset::factory()->create([
        'video_id' => $video->id,
        'type' => AssetType::ORIGINAL,
    ]);

    $session = UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'status' => UploadSessionStatus::COMPLETED,
    ]);

    expect($video->assets)->toHaveCount(1)
        ->and($video->assets->first()->id)->toBe($asset->id)
        ->and($video->uploadSessions)->toHaveCount(1)
        ->and($video->status)->toBe(VideoStatus::READY)
        ->and($video->visibility)->toBe(VideoVisibility::PUBLIC)
        ->and($video->tenant->id)->toBe($tenant->id)
        ->and($video->user)->toBeInstanceOf(User::class);
});

test('video query scopes filter correctly', function (): void {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $video1 = Video::factory()->create([
        'title' => 'Laravel 12 Tutorial',
        'description' => 'A comprehensive guide to Laravel',
        'status' => VideoStatus::READY,
        'visibility' => VideoVisibility::PUBLIC,
    ]);

    $video2 = Video::factory()->create([
        'title' => 'Vue 3 Mastery',
        'description' => 'Advanced component patterns',
        'status' => VideoStatus::DRAFT,
        'visibility' => VideoVisibility::PRIVATE,
    ]);

    // Search scope
    expect(Video::search('laravel')->count())->toBe(1)
        ->and(Video::search('patterns')->count())->toBe(1)
        ->and(Video::search('nonexistent')->count())->toBe(0)
        ->and(Video::search(null)->count())->toBe(2);

    // Status scope
    expect(Video::ofStatus(VideoStatus::READY)->count())->toBe(1)
        ->and(Video::ofStatus(VideoStatus::DRAFT)->count())->toBe(1);

    // Visibility scope
    expect(Video::ofVisibility(VideoVisibility::PUBLIC)->count())->toBe(1)
        ->and(Video::ofVisibility(VideoVisibility::PRIVATE)->count())->toBe(1);
});

test('upload session helper methods and relations work as expected', function (): void {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $video = Video::factory()->create();

    $activeSession = UploadSession::factory()->create([
        'video_id' => $video->id,
        'status' => UploadSessionStatus::PENDING,
        'expires_at' => now()->addHour(),
    ]);

    $expiredSession = UploadSession::factory()->create([
        'video_id' => $video->id,
        'status' => UploadSessionStatus::PENDING,
        'expires_at' => now()->subMinute(),
    ]);

    $completedSession = UploadSession::factory()->create([
        'video_id' => $video->id,
        'status' => UploadSessionStatus::COMPLETED,
        'expires_at' => now()->addHour(),
    ]);

    expect($activeSession->isExpired())->toBeFalse()
        ->and($activeSession->canAcceptParts())->toBeTrue()
        ->and($expiredSession->isExpired())->toBeTrue()
        ->and($expiredSession->canAcceptParts())->toBeFalse()
        ->and($completedSession->isExpired())->toBeFalse()
        ->and($completedSession->canAcceptParts())->toBeFalse()
        ->and($activeSession->video->id)->toBe($video->id)
        ->and($activeSession->tenant->id)->toBe($tenant->id);
});

test('tenant and user models have videos and uploadSessions relations', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    app()->instance('current_tenant', $tenant);

    $video = Video::factory()->create([
        'user_id' => $user->id,
    ]);

    $session = UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
    ]);

    expect($tenant->videos)->toHaveCount(1)
        ->and($tenant->videos->first()->id)->toBe($video->id)
        ->and($tenant->uploadSessions)->toHaveCount(1)
        ->and($tenant->uploadSessions->first()->id)->toBe($session->id)
        ->and($user->videos)->toHaveCount(1)
        ->and($user->videos->first()->id)->toBe($video->id);
});
