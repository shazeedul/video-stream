<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\Video;
use App\Domain\Videos\Models\VideoAsset;
use App\Enums\AssetType;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Enums\TenantUserStatus;
use App\Enums\UserStatus;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class VideoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedAcmeVideos();
        $this->seedNexusVideos();
    }

    /**
     * Seed demo videos for Acme Streaming Studio.
     */
    private function seedAcmeVideos(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'acme-streaming-studio'],
            [
                'name' => 'Acme Streaming Studio',
                'status' => TenantStatus::ACTIVE,
                'settings' => [
                    'timezone' => 'America/New_York',
                    'default_currency' => 'USD',
                    'max_upload_size_mb' => 2048,
                    'storage_limit_gb' => 100,
                ],
            ]
        );

        $user = User::query()->where('email', 'studio-owner@example.com')->first()
            ?? $tenant->users()->first()
            ?? $this->createFallbackUser('Studio Owner', 'studio-owner@example.com', $tenant);

        // 1. Product Keynote 2026 (Ready, Public, 120s, with assets)
        $this->createOrUpdateVideo(
            tenant: $tenant,
            user: $user,
            title: 'Product Keynote 2026',
            attributes: [
                'description' => 'Annual product keynote unveiling the next-generation video streaming architecture, ultra-low latency playback, and edge distribution.',
                'status' => VideoStatus::READY,
                'visibility' => VideoVisibility::PUBLIC,
                'duration_seconds' => 120,
                'thumbnail_path' => 'thumbnails/demo-keynote-2026.jpg',
                'metadata' => [
                    'resolution' => '1080p',
                    'width' => 1920,
                    'height' => 1080,
                    'fps' => 60,
                    'codec' => 'h264',
                ],
            ],
            assets: [
                [
                    'type' => AssetType::ORIGINAL,
                    'disk' => 'minio',
                    'object_key' => 'originals/{video_id}/keynote_2026_source.mp4',
                    'size_bytes' => 250 * 1024 * 1024,
                    'mime_type' => 'video/mp4',
                    'metadata' => [
                        'width' => 1920,
                        'height' => 1080,
                        'fps' => 60,
                        'codec' => 'h264',
                    ],
                ],
                [
                    'type' => AssetType::HLS_PLAYLIST,
                    'disk' => 'minio-processed',
                    'object_key' => 'processed/{video_id}/master.m3u8',
                    'size_bytes' => 4096,
                    'mime_type' => 'application/vnd.apple.mpegurl',
                    'metadata' => [
                        'renditions' => ['1080p', '720p', '480p'],
                    ],
                ],
            ]
        );

        // 2. Internal Engineering All-Hands (Ready, Private, 3600s, with assets)
        $this->createOrUpdateVideo(
            tenant: $tenant,
            user: $user,
            title: 'Internal Engineering All-Hands',
            attributes: [
                'description' => 'Quarterly engineering all-hands covering microservices roadmap, tenant isolation verification, and Q3 deployment milestones.',
                'status' => VideoStatus::READY,
                'visibility' => VideoVisibility::PRIVATE,
                'duration_seconds' => 3600,
                'thumbnail_path' => 'thumbnails/demo-all-hands.jpg',
                'metadata' => [
                    'resolution' => '1080p',
                    'width' => 1920,
                    'height' => 1080,
                    'fps' => 30,
                    'codec' => 'h264',
                ],
            ],
            assets: [
                [
                    'type' => AssetType::ORIGINAL,
                    'disk' => 'minio',
                    'object_key' => 'originals/{video_id}/all_hands_q1_source.mp4',
                    'size_bytes' => 1200 * 1024 * 1024,
                    'mime_type' => 'video/mp4',
                    'metadata' => [
                        'width' => 1920,
                        'height' => 1080,
                        'fps' => 30,
                    ],
                ],
                [
                    'type' => AssetType::HLS_PLAYLIST,
                    'disk' => 'minio-processed',
                    'object_key' => 'processed/{video_id}/master.m3u8',
                    'size_bytes' => 4096,
                    'mime_type' => 'application/vnd.apple.mpegurl',
                    'metadata' => [
                        'renditions' => ['1080p', '720p'],
                    ],
                ],
            ]
        );

        // 3. Draft Commercial Campaign (Draft, Private)
        $this->createOrUpdateVideo(
            tenant: $tenant,
            user: $user,
            title: 'Draft Commercial Campaign',
            attributes: [
                'description' => 'Initial storyboard rough cut and voiceover assembly for the upcoming Q3 product commercial.',
                'status' => VideoStatus::DRAFT,
                'visibility' => VideoVisibility::PRIVATE,
                'duration_seconds' => null,
                'thumbnail_path' => null,
                'metadata' => [
                    'stage' => 'storyboard',
                    'notes' => 'Pending marketing signoff',
                ],
            ],
            assets: []
        );
    }

    /**
     * Seed demo videos for Nexus Media Labs.
     */
    private function seedNexusVideos(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'nexus-media-labs'],
            [
                'name' => 'Nexus Media Labs',
                'status' => TenantStatus::ACTIVE,
                'settings' => [
                    'timezone' => 'Europe/London',
                    'default_currency' => 'EUR',
                    'max_upload_size_mb' => 4096,
                    'storage_limit_gb' => 250,
                ],
            ]
        );

        $user = User::query()->where('email', 'nexus-owner@example.com')->first()
            ?? $tenant->users()->first()
            ?? $this->createFallbackUser('Nexus Owner', 'nexus-owner@example.com', $tenant);

        // 1. Documentary Rough Cut (Uploading, Unlisted)
        $this->createOrUpdateVideo(
            tenant: $tenant,
            user: $user,
            title: 'Documentary Rough Cut',
            attributes: [
                'description' => 'Raw 4K camera footage assembly from the alpine wildlife shoot, pending colour grading and audio mix.',
                'status' => VideoStatus::UPLOADING,
                'visibility' => VideoVisibility::UNLISTED,
                'duration_seconds' => null,
                'thumbnail_path' => null,
                'metadata' => [
                    'project' => 'Alps Wildlife',
                    'camera' => 'ARRI Alexa Mini LF',
                ],
            ],
            assets: []
        );

        // 2. Color Grading Test Footage (Ready, Public, 45s)
        $this->createOrUpdateVideo(
            tenant: $tenant,
            user: $user,
            title: 'Color Grading Test Footage',
            attributes: [
                'description' => 'Wide dynamic range test footage showcasing Log-C color curves, highlight rolloff, and LUT fidelity.',
                'status' => VideoStatus::READY,
                'visibility' => VideoVisibility::PUBLIC,
                'duration_seconds' => 45,
                'thumbnail_path' => 'thumbnails/demo-color-grading.jpg',
                'metadata' => [
                    'resolution' => '4k',
                    'width' => 3840,
                    'height' => 2160,
                    'fps' => 24,
                    'codec' => 'prores',
                ],
            ],
            assets: [
                [
                    'type' => AssetType::ORIGINAL,
                    'disk' => 'minio',
                    'object_key' => 'originals/{video_id}/color_grading_test.mov',
                    'size_bytes' => 850 * 1024 * 1024,
                    'mime_type' => 'video/quicktime',
                    'metadata' => [
                        'width' => 3840,
                        'height' => 2160,
                        'fps' => 24,
                        'color_profile' => 'Apple ProRes 422 HQ',
                    ],
                ],
                [
                    'type' => AssetType::HLS_PLAYLIST,
                    'disk' => 'minio-processed',
                    'object_key' => 'processed/{video_id}/master.m3u8',
                    'size_bytes' => 4096,
                    'mime_type' => 'application/vnd.apple.mpegurl',
                    'metadata' => [
                        'renditions' => ['4k', '1080p', '720p'],
                    ],
                ],
            ]
        );
    }

    /**
     * Create or update a video record and its associated assets idempotently.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $assets
     */
    private function createOrUpdateVideo(
        Tenant $tenant,
        User $user,
        string $title,
        array $attributes,
        array $assets = []
    ): Video {
        /** @var Video $video */
        $video = Video::withoutGlobalScope('tenant')->firstOrNew([
            'tenant_id' => $tenant->id,
            'title' => $title,
        ]);

        $video->fill(array_merge([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'title' => $title,
        ], $attributes));
        $video->save();

        foreach ($assets as $assetData) {
            $type = $assetData['type'] instanceof AssetType ? $assetData['type'] : AssetType::from((string) $assetData['type']);
            $objectKey = str_replace('{video_id}', (string) $video->id, (string) $assetData['object_key']);

            VideoAsset::query()->firstOrCreate(
                [
                    'video_id' => $video->id,
                    'type' => $type,
                ],
                array_merge($assetData, [
                    'video_id' => $video->id,
                    'object_key' => $objectKey,
                ])
            );
        }

        return $video;
    }

    /**
     * Create a fallback user if neither email nor tenant members exist.
     */
    private function createFallbackUser(string $name, string $email, Tenant $tenant): User
    {
        /** @var User $user */
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => UserStatus::ACTIVE,
            ]
        );

        if (! $user->isTenantMember($tenant)) {
            $tenant->users()->attach($user->id, [
                'id' => (string) Str::uuid(),
                'role' => TenantRole::OWNER->value,
                'status' => TenantUserStatus::ACTIVE->value,
            ]);
        }

        return $user;
    }
}
