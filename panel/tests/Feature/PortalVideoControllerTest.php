<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Uploads\Contracts\MultipartUploadServiceInterface;
use App\Domain\Uploads\Models\UploadSession;
use App\Domain\Videos\Models\Video;
use App\Domain\Videos\Models\VideoAsset;
use App\Enums\AssetType;
use App\Enums\TenantRole;
use App\Enums\UploadSessionStatus;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config()->set('inertia.testing.ensure_pages_exist', false);
});

test('member can list videos and view video details in their organization', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Tutorial Video']);

    $this->actingAs($user)
        ->get(route('portal.videos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('portal/videos/index')->has('videos.data', 1));

    $this->actingAs($user)
        ->get(route('portal.videos.show', $video))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('portal/videos/show')->where('video.id', $video->id));
});

test('member can create draft video and update title', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $this->actingAs($user)
        ->post(route('portal.videos.store'), [
            'title' => 'My First Video',
            'description' => 'First test upload',
            'visibility' => VideoVisibility::PRIVATE->value,
        ])
        ->assertRedirect();

    $video = Video::where('title', 'My First Video')->first();
    expect($video)->not->toBeNull()
        ->and($video->tenant_id)->toBe($tenant->id)
        ->and($video->status)->toBe(VideoStatus::DRAFT);

    $this->actingAs($user)
        ->put(route('portal.videos.update', $video), [
            'title' => 'Updated Video Title',
        ])
        ->assertRedirect();

    expect($video->fresh()->title)->toBe('Updated Video Title');
});

test('videos index filters by search, status, and visibility and provides stats', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    app()->instance('current_tenant', $tenant);

    Video::factory()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Laravel Crash Course',
        'status' => VideoStatus::READY,
        'visibility' => VideoVisibility::PUBLIC,
    ]);
    Video::factory()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Vue Tutorial',
        'status' => VideoStatus::DRAFT,
        'visibility' => VideoVisibility::PRIVATE,
    ]);

    // Search filter
    $this->actingAs($user)
        ->get(route('portal.videos.index', ['search' => 'Crash Course']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('portal/videos/index')
            ->has('videos.data', 1)
            ->where('videos.data.0.title', 'Laravel Crash Course')
            ->has('stats')
        );

    // Status filter
    $this->actingAs($user)
        ->get(route('portal.videos.index', ['status' => VideoStatus::DRAFT->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('portal/videos/index')
            ->has('videos.data', 1)
            ->where('videos.data.0.title', 'Vue Tutorial')
        );

    // Visibility filter
    $this->actingAs($user)
        ->get(route('portal.videos.index', ['visibility' => VideoVisibility::PUBLIC->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('portal/videos/index')
            ->has('videos.data', 1)
            ->where('videos.data.0.title', 'Laravel Crash Course')
        );
});

test('viewer role can view videos but cannot create, update, or delete', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->create();
    $tenant->users()->attach($viewer->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::VIEWER->value]);

    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($viewer)
        ->get(route('portal.videos.index'))
        ->assertOk();

    $this->actingAs($viewer)
        ->get(route('portal.videos.show', $video))
        ->assertOk();

    $this->actingAs($viewer)
        ->post(route('portal.videos.store'), [
            'title' => 'Viewer Video',
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->put(route('portal.videos.update', $video), [
            'title' => 'Viewer Update',
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->delete(route('portal.videos.destroy', $video))
        ->assertForbidden();
});

test('member cannot update another members video or delete any video', function (): void {
    $tenant = Tenant::factory()->create();
    $member1 = User::factory()->create();
    $member2 = User::factory()->create();
    $tenant->users()->attach($member1->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);
    $tenant->users()->attach($member2->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    app()->instance('current_tenant', $tenant);
    $video1 = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $member1->id]);

    $this->actingAs($member2)
        ->put(route('portal.videos.update', $video1), [
            'title' => 'Unauthorized Update',
        ])
        ->assertForbidden();

    $this->actingAs($member1)
        ->delete(route('portal.videos.destroy', $video1))
        ->assertForbidden();
});

test('tenant admin can update and delete video and cleans up storage assets', function (): void {
    Storage::fake('minio');
    Storage::fake('thumbnails');

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $tenant->users()->attach($admin->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::ADMIN->value]);
    $tenant->users()->attach($member->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    app()->instance('current_tenant', $tenant);

    $video = Video::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $member->id,
        'thumbnail_path' => 'thumbnails/thumb-1.jpg',
    ]);

    Storage::disk('thumbnails')->put('thumbnails/thumb-1.jpg', 'fake-image');

    $asset = VideoAsset::factory()->create([
        'video_id' => $video->id,
        'disk' => 'minio',
        'object_key' => 'videos/original.mp4',
        'type' => AssetType::ORIGINAL,
    ]);

    Storage::disk('minio')->put('videos/original.mp4', 'fake-video-content');

    expect(Storage::disk('minio')->exists('videos/original.mp4'))->toBeTrue()
        ->and(Storage::disk('thumbnails')->exists('thumbnails/thumb-1.jpg'))->toBeTrue();

    // Admin can update
    $this->actingAs($admin)
        ->put(route('portal.videos.update', $video), [
            'title' => 'Admin Edited Title',
        ])
        ->assertRedirect();

    expect($video->fresh()->title)->toBe('Admin Edited Title');

    // Admin can delete
    $this->actingAs($admin)
        ->delete(route('portal.videos.destroy', $video))
        ->assertRedirect(route('portal.videos.index'));

    expect(Video::find($video->id))->toBeNull()
        ->and(VideoAsset::find($asset->id))->toBeNull()
        ->and(Storage::disk('minio')->exists('videos/original.mp4'))->toBeFalse()
        ->and(Storage::disk('thumbnails')->exists('thumbnails/thumb-1.jpg'))->toBeFalse();
});

test('cross-tenant video access returns 404', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userB = User::factory()->create();
    $tenantB->users()->attach($userB->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::ADMIN->value]);

    app()->instance('current_tenant', $tenantA);
    $videoA = Video::factory()->create(['tenant_id' => $tenantA->id]);

    app()->instance('current_tenant', $tenantB);

    $this->actingAs($userB)
        ->get(route('portal.videos.show', $videoA))
        ->assertNotFound();

    $this->actingAs($userB)
        ->put(route('portal.videos.update', $videoA), ['title' => 'Hacked'])
        ->assertNotFound();

    $this->actingAs($userB)
        ->delete(route('portal.videos.destroy', $videoA))
        ->assertNotFound();
});

test('store and update video validation rules', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    app()->instance('current_tenant', $tenant);
    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);

    // Store with empty title
    $this->actingAs($user)
        ->post(route('portal.videos.store'), [
            'title' => '',
        ])
        ->assertSessionHasErrors(['title']);

    // Store with invalid visibility
    $this->actingAs($user)
        ->post(route('portal.videos.store'), [
            'title' => 'Valid Title',
            'visibility' => 'invalid-visibility',
        ])
        ->assertSessionHasErrors(['visibility']);

    // Update with invalid visibility
    $this->actingAs($user)
        ->put(route('portal.videos.update', $video), [
            'visibility' => 'invalid-visibility',
        ])
        ->assertSessionHasErrors(['visibility']);
});

test('member can create draft video and receive json response', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $response = $this->actingAs($user)
        ->postJson(route('portal.videos.store'), [
            'title' => 'API Draft Video',
            'description' => 'Draft created via API request',
            'visibility' => VideoVisibility::PRIVATE->value,
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'video' => ['id', 'title', 'description', 'status', 'visibility', 'tenant_id'],
            'id',
        ])
        ->assertJsonPath('video.title', 'API Draft Video');

    $video = Video::where('title', 'API Draft Video')->first();
    expect($video)->not->toBeNull()
        ->and($response->json('id'))->toBe($video->id);
});

test('deleting video aborts pending upload sessions to clean up partial chunks', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    $tenant->users()->attach($admin->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::ADMIN->value]);

    app()->instance('current_tenant', $tenant);

    $video = Video::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $admin->id]);
    $session = UploadSession::factory()->create([
        'tenant_id' => $tenant->id,
        'video_id' => $video->id,
        'status' => UploadSessionStatus::PENDING,
    ]);

    $mockUploadService = Mockery::mock(MultipartUploadServiceInterface::class);
    $mockUploadService->shouldReceive('abortUpload')
        ->once()
        ->with(Mockery::on(fn ($s) => $s->id === $session->id));
    app()->instance(MultipartUploadServiceInterface::class, $mockUploadService);

    $this->actingAs($admin)
        ->delete(route('portal.videos.destroy', $video))
        ->assertRedirect(route('portal.videos.index'));

    expect(Video::find($video->id))->toBeNull();
});
