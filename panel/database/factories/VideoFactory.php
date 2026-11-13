<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\Video;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Video>
 */
final class VideoFactory extends Factory
{
    protected $model = Video::class;

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
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'visibility' => VideoVisibility::PRIVATE,
            'status' => VideoStatus::DRAFT,
            'duration_seconds' => fake()->numberBetween(10, 3600),
            'thumbnail_path' => null,
            'metadata' => [
                'width' => 1920,
                'height' => 1080,
                'fps' => 30,
            ],
        ];
    }

    /**
     * Indicate the tenant that owns the video.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Indicate that the video is ready.
     */
    public function ready(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VideoStatus::READY,
        ]);
    }

    /**
     * Indicate that the video processing failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VideoStatus::FAILED,
        ]);
    }

    /**
     * Indicate that the video has public visibility.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes): array => [
            'visibility' => VideoVisibility::PUBLIC,
        ]);
    }

    /**
     * Indicate that the video has unlisted visibility.
     */
    public function unlisted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'visibility' => VideoVisibility::UNLISTED,
        ]);
    }

    /**
     * Indicate that the video has private visibility.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes): array => [
            'visibility' => VideoVisibility::PRIVATE,
        ]);
    }
}
