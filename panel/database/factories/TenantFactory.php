<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tenants\Models\Tenant;
use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
final class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'status' => TenantStatus::ACTIVE,
            'settings' => [
                'theme' => 'light',
            ],
        ];
    }

    /**
     * Indicate that the tenant is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TenantStatus::ACTIVE,
        ]);
    }

    /**
     * Indicate that the tenant is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TenantStatus::SUSPENDED,
        ]);
    }

    /**
     * Indicate that the tenant is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TenantStatus::ARCHIVED,
        ]);
    }
}
