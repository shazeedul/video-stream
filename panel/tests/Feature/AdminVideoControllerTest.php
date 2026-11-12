<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Uploads\Models\UploadSession;
use App\Domain\Videos\Models\Video;
use App\Domain\Videos\Models\VideoAsset;
use App\Enums\AssetType;
use App\Enums\VideoStatus;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config()->set('inertia.testing.ensure_pages_exist', false);
});

test('platform admin can view videos across all tenants', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $tenantA = Tenant::factory()->create(['name' => 'Studio A']);
    $tenantB = Tenant::factory()->create(['name' => 'Studio B']);

    Video::factory()->create(['tenant_id' => $tenantA->id, 'title' => 'Video A']);
    Video::factory()->create(['tenant_id' => $tenantB->id, 'title' => 'Video B']);

    $this->actingAs($admin)
        ->get(route('admin.videos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/videos/index')
            ->has('videos.data', 2)
            ->has('tenants', 2)
            ->has('filters')
        );
});

test('regular user cannot access admin video endpoints', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('admin.panel.view');

    $this->actingAs($user)
        ->get(route('admin.videos.index'))
        ->assertForbidden();
});

test('portal user without admin panel access is redirected', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.videos.index'))
        ->assertRedirect(route('portal.dashboard'));
});

test('platform admin can filter videos by search term across title and description', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $tenant = Tenant::factory()->create();
    Video::factory()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Deep Dive into Laravel',
        'description' => 'A backend masterclass',
    ]);
    Video::factory()->create([
        'tenant_id' => $tenant->id,
        'title' => 'React Frontend Patterns',
        'description' => 'Component architecture',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.videos.index', ['search' => 'masterclass']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/videos/index')
            ->has('videos.data', 1)
            ->where('videos.data.0.title', 'Deep Dive into Laravel')
            ->where('filters.search', 'masterclass')
        );
});

test('platform admin can filter videos by tenant id', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $tenantA = Tenant::factory()->create(['name' => 'Alpha Studio']);
    $tenantB = Tenant::factory()->create(['name' => 'Beta Studio']);

    Video::factory()->create(['tenant_id' => $tenantA->id, 'title' => 'Alpha Feature Film']);
    Video::factory()->create(['tenant_id' => $tenantB->id, 'title' => 'Beta Documentary']);

    $response = $this->actingAs($admin)->get(route('admin.videos.index', ['tenant_id' => $tenantA->id]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/videos/index')
            ->has('videos.data', 1)
            ->where('videos.data.0.title', 'Alpha Feature Film')
            ->where('filters.tenant_id', $tenantA->id)
        );
});

test('platform admin can filter videos by status', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $tenant = Tenant::factory()->create();
    Video::factory()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Published Video',
        'status' => VideoStatus::READY,
    ]);
    Video::factory()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Draft Video',
        'status' => VideoStatus::DRAFT,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.videos.index', ['status' => VideoStatus::READY->value]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/videos/index')
            ->has('videos.data', 1)
            ->where('videos.data.0.title', 'Published Video')
            ->where('filters.status', VideoStatus::READY->value)
        );
});

test('platform admin can view video details with relationships across any tenant', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $tenant = Tenant::factory()->create(['name' => 'Paramount Cinema']);
    $creator = User::factory()->create(['name' => 'Christopher Director']);

    $video = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $creator->id,
        'title' => 'Interstellar Journey',
    ]);

    $asset = VideoAsset::factory()->create([
        'video_id' => $video->id,
        'disk' => 'minio',
        'object_key' => 'videos/journey.mp4',
        'type' => AssetType::ORIGINAL,
    ]);

    $session = UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.videos.show', $video));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/videos/show')
            ->where('video.id', $video->id)
            ->where('video.title', 'Interstellar Journey')
            ->where('video.tenant.id', $tenant->id)
            ->where('video.tenant.name', 'Paramount Cinema')
            ->where('video.user.id', $creator->id)
            ->where('video.user.name', 'Christopher Director')
            ->has('video.assets', 1)
            ->where('video.assets.0.id', $asset->id)
            ->has('video.upload_sessions', 1)
            ->where('video.upload_sessions.0.id', $session->id)
        );
});

test('viewing non existent video returns 404', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('admin.videos.show', '00000000-0000-0000-0000-000000000000'))
        ->assertNotFound();
});

test('platform admin can delete video and purge storage assets across any tenant', function (): void {
    Storage::fake('minio');
    Storage::fake('thumbnails');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $tenant = Tenant::factory()->create();
    $video = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'thumbnail_path' => 'thumbnails/admin-thumb.jpg',
    ]);

    Storage::disk('thumbnails')->put('thumbnails/admin-thumb.jpg', 'fake-thumbnail-data');

    $asset = VideoAsset::factory()->create([
        'video_id' => $video->id,
        'disk' => 'minio',
        'object_key' => 'videos/admin-original.mp4',
        'type' => AssetType::ORIGINAL,
    ]);

    Storage::disk('minio')->put('videos/admin-original.mp4', 'fake-video-data');

    expect(Storage::disk('minio')->exists('videos/admin-original.mp4'))->toBeTrue()
        ->and(Storage::disk('thumbnails')->exists('thumbnails/admin-thumb.jpg'))->toBeTrue();

    $response = $this->actingAs($admin)->delete(route('admin.videos.destroy', $video));

    $response->assertRedirect(route('admin.videos.index'))
        ->assertSessionHas('success');

    expect(Video::find($video->id))->toBeNull()
        ->and(VideoAsset::find($asset->id))->toBeNull()
        ->and(Storage::disk('minio')->exists('videos/admin-original.mp4'))->toBeFalse()
        ->and(Storage::disk('thumbnails')->exists('thumbnails/admin-thumb.jpg'))->toBeFalse();
});

test('user without videos.delete permission cannot delete video', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('admin.panel.view');
    $user->givePermissionTo('videos.view');

    $tenant = Tenant::factory()->create();
    $video = Video::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->delete(route('admin.videos.destroy', $video))
        ->assertForbidden();

    expect(Video::find($video->id))->not()->toBeNull();
});

test('deleting non existent video returns 404', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->delete(route('admin.videos.destroy', '00000000-0000-0000-0000-000000000000'))
        ->assertNotFound();
});
