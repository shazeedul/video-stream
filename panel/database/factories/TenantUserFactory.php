<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Models\TenantUser;
use App\Enums\TenantRole;
use App\Enums\TenantUserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TenantUser>
 */
final class TenantUserFactory extends Factory
{
    protected $model = TenantUser::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'role' => TenantRole::MEMBER,
            'status' => TenantUserStatus::ACTIVE,
        ];
    }

    /**
     * Indicate that the membership role is owner.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => TenantRole::OWNER,
        ]);
    }

    /**
     * Indicate that the membership role is admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => TenantRole::ADMIN,
        ]);
    }

    /**
     * Indicate that the membership role is member.
     */
    public function member(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => TenantRole::MEMBER,
        ]);
    }

    /**
     * Indicate that the membership role is viewer.
     */
    public function viewer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => TenantRole::VIEWER,
        ]);
    }

    /**
     * Indicate that the membership status is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TenantUserStatus::ACTIVE,
        ]);
    }

    /**
     * Indicate that the membership status is invited.
     */
    public function invited(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TenantUserStatus::INVITED,
        ]);
    }

    /**
     * Indicate that the membership status is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TenantUserStatus::SUSPENDED,
        ]);
    }
}
