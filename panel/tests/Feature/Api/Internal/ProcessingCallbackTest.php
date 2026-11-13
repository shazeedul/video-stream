<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Internal;

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\Video;
use App\Enums\VideoStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProcessingCallbackTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-internal-secret-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.internal.secret' => $this->secret]);
    }

    public function test_it_rejects_unauthorized_callback_requests(): void
    {
        $response = $this->postJson('/api/v1/internal/processing/callback', [
            'video_id' => '019324c3-e836-7db0-bd1b-c40c83a5316e',
            'status' => 'probing',
        ]);

        $response->assertStatus(401);
    }

    public function test_it_rejects_invalid_secret_token(): void
    {
        $response = $this->withHeaders(['X-Internal-Secret' => 'wrong-secret'])
            ->postJson('/api/v1/internal/processing/callback', [
                'video_id' => '019324c3-e836-7db0-bd1b-c40c83a5316e',
                'status' => 'probing',
            ]);

        $response->assertStatus(401);
    }

    public function test_it_validates_required_fields(): void
    {
        $response = $this->withHeaders(['X-Internal-Secret' => $this->secret])
            ->postJson('/api/v1/internal/processing/callback', [
                'status' => 'invalid-status',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['job_id', 'video_id', 'status']);
    }

    public function test_it_transitions_video_to_probing(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $video = Video::factory()->forTenant($tenant)->create([
            'user_id' => $user->id,
            'status' => VideoStatus::QUEUED,
        ]);

        $response = $this->withHeaders(['X-Internal-Secret' => $this->secret])
            ->postJson('/api/v1/internal/processing/callback', [
                'job_id' => '019324c4-7bf3-7521-9989-d419b4ef08f1',
                'video_id' => $video->id,
                'status' => 'probing',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'video_id' => $video->id,
            ]);
        $this->assertEquals(VideoStatus::PROBING, $video->fresh()->status);
    }

    public function test_it_updates_video_metadata_and_duration_on_probed(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $video = Video::factory()->forTenant($tenant)->create([
            'user_id' => $user->id,
            'status' => VideoStatus::PROBING,
        ]);

        $metadata = [
            'format' => 'mov,mp4',
            'size_bytes' => 1048576,
            'bitrate' => 1200000,
            'video' => [
                'codec' => 'h264',
                'width' => 1920,
                'height' => 1080,
                'fps' => 30.0,
            ],
            'audio' => [
                'codec' => 'aac',
                'channels' => 2,
            ],
        ];

        $response = $this->withHeaders(['X-Internal-Secret' => $this->secret])
            ->postJson('/api/v1/internal/processing/callback', [
                'job_id' => '019324c4-7bf3-7521-9989-d419b4ef08f1',
                'video_id' => $video->id,
                'status' => 'probed',
                'duration_seconds' => 42,
                'metadata' => $metadata,
            ]);

        $response->assertOk();
        $fresh = $video->fresh();
        $this->assertEquals(42, $fresh->duration_seconds);
        $this->assertEquals('h264', $fresh->metadata['video']['codec']);
        $this->assertEquals(1920, $fresh->metadata['video']['width']);
    }

    public function test_it_marks_video_failed_on_error(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $video = Video::factory()->forTenant($tenant)->create([
            'user_id' => $user->id,
            'status' => VideoStatus::PROBING,
        ]);

        $response = $this->withHeaders(['X-Internal-Secret' => $this->secret])
            ->postJson('/api/v1/internal/processing/callback', [
                'job_id' => '019324c4-7bf3-7521-9989-d419b4ef08f1',
                'video_id' => $video->id,
                'status' => 'failed',
                'error' => 'Unsupported container format or corrupt file header',
            ]);

        $response->assertOk();
        $fresh = $video->fresh();
        $this->assertEquals(VideoStatus::FAILED, $fresh->status);
        $this->assertEquals('Unsupported container format or corrupt file header', $fresh->metadata['error']);
    }
}
