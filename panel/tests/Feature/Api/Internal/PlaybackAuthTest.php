<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Internal;

use App\Domain\Playback\Contracts\PlaybackTokenServiceInterface;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Models\TenantUser;
use App\Domain\Videos\Models\Video;
use App\Enums\VideoStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PlaybackAuthTest extends TestCase
{
    use RefreshDatabase;

    private PlaybackTokenServiceInterface $tokenService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenService = app(PlaybackTokenServiceInterface::class);
    }

    public function test_valid_token_for_ready_video_returns_200_with_tenant_id_header(): void
    {
        $tenant = Tenant::factory()->create();
        $video = Video::factory()->forTenant($tenant)->ready()->create();

        $expiresAt = time() + 3600;
        $token = $this->tokenService->generateToken((string) $video->id, $expiresAt);

        $response = $this->get("/api/v1/internal/playback/auth?video_id={$video->id}&token={$token}&expires={$expiresAt}");

        $response->assertOk();
        $response->assertHeader('X-Tenant-Id', $tenant->id);
    }

    public function test_valid_token_with_x_video_id_header_returns_200_with_tenant_id_header(): void
    {
        $tenant = Tenant::factory()->create();
        $video = Video::factory()->forTenant($tenant)->ready()->create();

        $expiresAt = time() + 3600;
        $token = $this->tokenService->generateToken((string) $video->id, $expiresAt);

        $response = $this->withHeaders(['X-Video-Id' => (string) $video->id])
            ->get("/api/v1/internal/playback/auth?token={$token}&expires={$expiresAt}");

        $response->assertOk();
        $response->assertHeader('X-Tenant-Id', $tenant->id);
    }

    public function test_expired_token_returns_403_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $video = Video::factory()->forTenant($tenant)->ready()->create();

        $expiresAt = time() - 3600;
        $token = $this->tokenService->generateToken((string) $video->id, $expiresAt);

        $response = $this->get("/api/v1/internal/playback/auth?video_id={$video->id}&token={$token}&expires={$expiresAt}");

        $response->assertStatus(403);
    }

    public function test_invalid_token_signature_returns_403_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $video = Video::factory()->forTenant($tenant)->ready()->create();

        $expiresAt = time() + 3600;
        $token = 'invalid-tampered-token-signature';

        $response = $this->get("/api/v1/internal/playback/auth?video_id={$video->id}&token={$token}&expires={$expiresAt}");

        $response->assertStatus(403);
    }

    public function test_transcoding_video_returns_403_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $video = Video::factory()->forTenant($tenant)->create([
            'status' => VideoStatus::TRANSCODING,
        ]);

        $expiresAt = time() + 3600;
        $token = $this->tokenService->generateToken((string) $video->id, $expiresAt);

        $response = $this->get("/api/v1/internal/playback/auth?video_id={$video->id}&token={$token}&expires={$expiresAt}");

        $response->assertStatus(403);
    }

    public function test_failed_video_returns_403_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $video = Video::factory()->forTenant($tenant)->failed()->create();

        $expiresAt = time() + 3600;
        $token = $this->tokenService->generateToken((string) $video->id, $expiresAt);

        $response = $this->get("/api/v1/internal/playback/auth?video_id={$video->id}&token={$token}&expires={$expiresAt}");

        $response->assertStatus(403);
    }

    public function test_non_existent_video_returns_403_forbidden(): void
    {
        $nonExistentId = (string) Str::uuid();
        $expiresAt = time() + 3600;
        $token = $this->tokenService->generateToken($nonExistentId, $expiresAt);

        $response = $this->get("/api/v1/internal/playback/auth?video_id={$nonExistentId}&token={$token}&expires={$expiresAt}");

        $response->assertStatus(403);
    }

    public function test_missing_video_id_returns_400_bad_request(): void
    {
        $response = $this->get('/api/v1/internal/playback/auth');

        $response->assertStatus(400);
    }

    public function test_private_video_without_valid_token_returns_403_for_guest(): void
    {
        $tenant = Tenant::factory()->create();
        $video = Video::factory()->forTenant($tenant)->ready()->private()->create();

        $response = $this->get("/api/v1/internal/playback/auth?video_id={$video->id}");

        $response->assertStatus(403);
    }

    public function test_private_video_without_token_returns_200_for_authenticated_tenant_member(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        TenantUser::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        $video = Video::factory()->forTenant($tenant)->ready()->private()->create();

        $response = $this->actingAs($user)
            ->get("/api/v1/internal/playback/auth?video_id={$video->id}");

        $response->assertOk();
        $response->assertHeader('X-Tenant-Id', $tenant->id);
    }

    public function test_private_video_returns_403_for_authenticated_user_from_different_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userB = User::factory()->create();
        TenantUser::factory()->create([
            'tenant_id' => $tenantB->id,
            'user_id' => $userB->id,
        ]);

        $videoA = Video::factory()->forTenant($tenantA)->ready()->private()->create();

        $response = $this->actingAs($userB)
            ->get("/api/v1/internal/playback/auth?video_id={$videoA->id}");

        $response->assertStatus(403);
    }
}
