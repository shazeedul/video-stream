<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Usage\Models\TenantUsageRecord;
use App\Domain\Videos\Models\Video;
use App\Domain\Videos\Models\VideoAsset;
use App\Enums\AssetType;
use App\Enums\TenantRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
});

it('displays portal settings usage page for tenant member', function (): void {
    $tenant = Tenant::factory()->create([
        'name' => 'Acme Media',
        'settings' => ['storage_quota_bytes' => 10 * 1024 * 1024 * 1024],
    ]);

    $user = User::factory()->create();
    $user->assignRole('user');
    $tenant->users()->attach($user->id, [
        'id' => (string) str()->uuid(),
        'role' => TenantRole::MEMBER->value,
    ]);

    $video = Video::factory()->forTenant($tenant)->create([
        'user_id' => $user->id,
    ]);

    // 100 MB Original
    VideoAsset::factory()->create([
        'video_id' => $video->id,
        'type' => AssetType::ORIGINAL,
        'size_bytes' => 100_000_000,
    ]);

    // 40 MB HLS (20MB playlist + 20MB segment)
    VideoAsset::factory()->create([
        'video_id' => $video->id,
        'type' => AssetType::HLS_PLAYLIST,
        'size_bytes' => 20_000_000,
    ]);
    VideoAsset::factory()->create([
        'video_id' => $video->id,
        'type' => AssetType::HLS_SEGMENT,
        'size_bytes' => 20_000_000,
    ]);

    // 5 MB Thumbnail
    VideoAsset::factory()->create([
        'video_id' => $video->id,
        'type' => AssetType::THUMBNAIL,
        'size_bytes' => 5_000_000,
    ]);

    // 30-day Usage records
    TenantUsageRecord::create([
        'tenant_id' => $tenant->id,
        'date' => now()->subDays(5)->toDateString(),
        'storage_bytes' => 145_000_000,
        'egress_bytes' => 50_000_000,
        'view_count' => 120,
        'video_count' => 1,
    ]);

    TenantUsageRecord::create([
        'tenant_id' => $tenant->id,
        'date' => now()->subDays(2)->toDateString(),
        'storage_bytes' => 145_000_000,
        'egress_bytes' => 75_000_000,
        'view_count' => 230,
        'video_count' => 1,
    ]);

    actingAs($user)
        ->get(route('portal.settings.usage'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/settings/usage')
            ->has('storage')
            ->has('breakdown')
            ->has('trends')
            ->where('storage.used_bytes', 145_000_000)
            ->where('storage.quota_bytes', 10 * 1024 * 1024 * 1024)
            ->where('breakdown.original.bytes', 100_000_000)
            ->where('breakdown.hls.bytes', 40_000_000)
            ->where('breakdown.thumbnail.bytes', 5_000_000)
            ->where('trends.total_views_30d', 350)
            ->where('trends.total_egress_bytes_30d', 125_000_000)
            ->has('trends.records', 2)
        );
});

it('returns 404 for portal settings usage if user has no active tenant', function (): void {
    $user = portalUser();

    $mockContext = Mockery::mock(App\Domain\Tenants\Contracts\TenantContextServiceInterface::class);
    $mockContext->shouldReceive('getCurrentTenant')->andReturn(null);
    $mockContext->shouldReceive('getActiveTenant')->andReturn(null);
    app()->instance(App\Domain\Tenants\Contracts\TenantContextServiceInterface::class, $mockContext);

    actingAs($user)
        ->get(route('portal.settings.usage'))
        ->assertNotFound();
});

it('redirects unauthenticated users to login for portal settings usage', function (): void {
    test()
        ->get(route('portal.settings.usage'))
        ->assertRedirect(route('login'));
});
