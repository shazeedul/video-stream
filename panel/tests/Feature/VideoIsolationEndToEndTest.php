<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\Video;
use App\Enums\AssetType;
use App\Enums\TenantRole;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\User;
use Database\Seeders\VideoSeeder;

beforeEach(function (): void {
    config()->set('inertia.testing.ensure_pages_exist', false);
});

test('end-to-end multi-tenant video isolation and upload lifecycle enforcement', function (): void {
    // 1. Setup Tenant A and Tenant B with owners and members
    $tenantA = Tenant::factory()->create(['name' => 'Studio A', 'slug' => 'studio-a']);
    $userA = User::factory()->create(['name' => 'Alice']);
    $tenantA->users()->attach($userA->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $tenantB = Tenant::factory()->create(['name' => 'Studio B', 'slug' => 'studio-b']);
    $userB = User::factory()->create(['name' => 'Bob']);
    $tenantB->users()->attach($userB->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    // 2. Alice creates video in Tenant A
    app()->instance('current_tenant', $tenantA);
    $videoA = Video::factory()->create(['tenant_id' => $tenantA->id, 'user_id' => $userA->id, 'title' => 'Alice Secret Video']);

    // 3. Bob cannot view Alice's video in the portal list or details
    app()->instance('current_tenant', $tenantB);
    $this->actingAs($userB)
        ->get(route('portal.videos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('videos.data', []));

    $this->actingAs($userB)
        ->get(route('portal.videos.show', $videoA))
        ->assertNotFound();

    // 4. Bob cannot initiate, complete, or abort uploads for Alice's video
    $this->actingAs($userB)
        ->postJson(route('portal.videos.upload.initiate', $videoA), [
            'filename' => 'hack.mp4',
            'file_size' => 10 * 1024 * 1024,
            'content_type' => 'video/mp4',
        ])
        ->assertNotFound();

    // 5. Alice can update her video, but cannot update videos from Tenant B
    app()->instance('current_tenant', $tenantA);
    $this->actingAs($userA)
        ->put(route('portal.videos.update', $videoA), [
            'title' => 'Alice Updated Video',
            'description' => 'Updated description',
            'visibility' => 'private',
        ])
        ->assertSessionHasNoErrors();
    expect($videoA->fresh()->title)->toBe('Alice Updated Video');

    // Bob cannot modify or delete Alice's video
    app()->instance('current_tenant', $tenantB);
    $this->actingAs($userB)
        ->put(route('portal.videos.update', $videoA), [
            'title' => 'Bob Hacked Video',
        ])
        ->assertNotFound();
    expect($videoA->fresh()->title)->toBe('Alice Updated Video');

    $this->actingAs($userB)
        ->delete(route('portal.videos.destroy', $videoA))
        ->assertNotFound();
    expect(Video::withoutGlobalScope('tenant')->find($videoA->id))->not->toBeNull();

    // Bob creates a video in Tenant B, Alice cannot update it
    $videoB = Video::factory()->create(['tenant_id' => $tenantB->id, 'user_id' => $userB->id, 'title' => 'Bob Secret Video']);
    app()->instance('current_tenant', $tenantA);
    $this->actingAs($userA)
        ->put(route('portal.videos.update', $videoB), [
            'title' => 'Alice Hacked Bob Video',
        ])
        ->assertNotFound();
    expect($videoB->fresh()->title)->toBe('Bob Secret Video');
});

test('video seeder provisions realistic demo videos and assets across tenants', function (): void {
    $this->seed(VideoSeeder::class);

    $acme = Tenant::query()->where('slug', 'acme-streaming-studio')->first();
    expect($acme)->not->toBeNull();

    $nexus = Tenant::query()->where('slug', 'nexus-media-labs')->first();
    expect($nexus)->not->toBeNull();

    // Acme videos verification
    $acmeVideos = Video::withoutGlobalScope('tenant')->where('tenant_id', $acme->id)->get();
    expect($acmeVideos)->toHaveCount(3);

    $keynote = $acmeVideos->firstWhere('title', 'Product Keynote 2026');
    expect($keynote)->not->toBeNull()
        ->and($keynote->status)->toBe(VideoStatus::READY)
        ->and($keynote->visibility)->toBe(VideoVisibility::PUBLIC)
        ->and($keynote->duration_seconds)->toBe(120);

    expect($keynote->assets)->toHaveCount(2);
    expect($keynote->assets->pluck('type')->all())->toContain(AssetType::ORIGINAL, AssetType::HLS_PLAYLIST);

    $allHands = $acmeVideos->firstWhere('title', 'Internal Engineering All-Hands');
    expect($allHands)->not->toBeNull()
        ->and($allHands->status)->toBe(VideoStatus::READY)
        ->and($allHands->visibility)->toBe(VideoVisibility::PRIVATE)
        ->and($allHands->duration_seconds)->toBe(3600);

    expect($allHands->assets)->toHaveCount(2);

    $campaign = $acmeVideos->firstWhere('title', 'Draft Commercial Campaign');
    expect($campaign)->not->toBeNull()
        ->and($campaign->status)->toBe(VideoStatus::DRAFT)
        ->and($campaign->visibility)->toBe(VideoVisibility::PRIVATE)
        ->and($campaign->assets)->toHaveCount(0);

    // Nexus videos verification
    $nexusVideos = Video::withoutGlobalScope('tenant')->where('tenant_id', $nexus->id)->get();
    expect($nexusVideos)->toHaveCount(2);

    $roughCut = $nexusVideos->firstWhere('title', 'Documentary Rough Cut');
    expect($roughCut)->not->toBeNull()
        ->and($roughCut->status)->toBe(VideoStatus::UPLOADING)
        ->and($roughCut->visibility)->toBe(VideoVisibility::UNLISTED);

    $colorTest = $nexusVideos->firstWhere('title', 'Color Grading Test Footage');
    expect($colorTest)->not->toBeNull()
        ->and($colorTest->status)->toBe(VideoStatus::READY)
        ->and($colorTest->visibility)->toBe(VideoVisibility::PUBLIC)
        ->and($colorTest->duration_seconds)->toBe(45);

    expect($colorTest->assets)->toHaveCount(2);
});
