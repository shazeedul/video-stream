<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Uploads\Models\UploadSession;
use App\Domain\Videos\Models\Video;
use App\Enums\UploadSessionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UploadSession>
 */
final class UploadSessionFactory extends Factory
{
    protected $model = UploadSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => static function (): string {
                if (app()->bound('current_tenant')) {
                    /** @var mixed $tenant */
                    $tenant = app('current_tenant');
                    if ($tenant instanceof Tenant) {
                        return $tenant->id;
                    }
                    if (is_object($tenant) && isset($tenant->id)) {
                        return (string) $tenant->id;
                    }
                    if (is_string($tenant) && $tenant !== '') {
                        return $tenant;
                    }
                }

                return Tenant::factory()->create()->id;
            },
            'video_id' => Video::factory(),
            'upload_id' => 'up_'.Str::random(24),
            'disk' => 'minio_originals',
            'object_key' => 'uploads/'.(string) Str::uuid().'/source.mp4',
            'part_size' => 5 * 1024 * 1024,
            'total_parts' => 3,
            'status' => UploadSessionStatus::PENDING,
            'expires_at' => now()->addHours(24),
        ];
    }

    /**
     * Indicate that the upload session is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => UploadSessionStatus::COMPLETED,
        ]);
    }

    /**
     * Indicate that the upload session was aborted.
     */
    public function aborted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => UploadSessionStatus::ABORTED,
        ]);
    }

    /**
     * Indicate that the upload session is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => UploadSessionStatus::EXPIRED,
            'expires_at' => now()->subHour(),
        ]);
    }
}
