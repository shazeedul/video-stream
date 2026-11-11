<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
final class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    private static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'phone' => fake()->boolean(70) ? fake()->phoneNumber() : null,
            'bio' => fake()->boolean(40) ? fake()->paragraph() : null,
            'company' => null,
            'address' => fake()->boolean(60) ? fake()->streetAddress() : null,
            'city' => fake()->boolean(60) ? fake()->city() : null,
            'state' => fake()->boolean(60) ? fake()->state() : null,
            'zip_code' => fake()->boolean(60) ? fake()->postcode() : null,
            'status' => fake()->boolean(95) ? UserStatus::ACTIVE : UserStatus::INACTIVE,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create an agent user.
     */
    public function agent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'company' => fake()->randomElement([
                'Century 21',
                'Coldwell Banker',
                'RE/MAX',
                'Keller Williams',
                'Berkshire Hathaway HomeServices',
                'Compass',
                "Sotheby's International Realty",
                'Fortune Home Realty',
            ]),
            'bio' => fake()->paragraph(3),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'zip_code' => fake()->postcode(),
            'status' => UserStatus::ACTIVE,
        ]);
    }

    /**
     * Create a customer user.
     */
    public function customer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'company' => null,
            'bio' => fake()->boolean(20) ? fake()->sentence() : null,
        ]);
    }

    /**
     * Create an admin user.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'company' => 'Starter Kit',
            'bio' => 'Administrator',
            'phone' => fake()->phoneNumber(),
            'status' => UserStatus::ACTIVE,
        ]);
    }

    /**
     * Create an inactive user.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => UserStatus::INACTIVE,
        ]);
    }

    /**
     * Create a suspended user.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => UserStatus::SUSPENDED,
            'status_reason' => 'Account suspended by administrator.',
        ]);
    }

    /**
     * Create a user with complete profile.
     */
    public function withCompleteProfile(): static
    {
        return $this->state(fn (array $attributes): array => [
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'zip_code' => fake()->postcode(),
            'bio' => fake()->paragraph(),
        ]);
    }
}
