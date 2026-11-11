<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SocialAccount>
 */
final class SocialAccountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SocialAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $provider = fake()->randomElement(['google', 'github', 'facebook', 'twitter', 'linkedin']);

        return [
            'user_id' => User::factory(),
            'provider' => $provider,
            'provider_id' => fake()->unique()->randomNumber(8),
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->optional()->sha256(),
            'expires_at' => fake()->optional()->dateTimeBetween('now', '+1 year'),
            'metadata' => [
                'avatar' => fake()->imageUrl(200, 200, 'people'),
                'email' => fake()->safeEmail(),
                'name' => fake()->name(),
                'username' => fake()->userName(),
            ],
        ];
    }

    /**
     * Indicate that the social account is for Google.
     */
    public function google(): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => 'google',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'avatar' => 'https://lh3.googleusercontent.com/a/default-user=s96-c',
            ]),
        ]);
    }

    /**
     * Indicate that the social account is for GitHub.
     */
    public function github(): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => 'github',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'avatar' => 'https://avatars.githubusercontent.com/u/12345?v=4',
            ]),
        ]);
    }

    /**
     * Indicate that the token is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subHour(),
        ]);
    }
}
