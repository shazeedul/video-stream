<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuthProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuthProvider>
 */
final class AuthProviderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AuthProvider::class;

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
            'provider_id' => fake()->unique()->numerify('##########'),
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'metadata' => [
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
                'avatar' => fake()->imageUrl(200, 200, 'people'),
                'username' => fake()->userName(),
            ],
            'expires_at' => fake()->optional()->dateTimeBetween('now', '+1 year'),
        ];
    }
}
