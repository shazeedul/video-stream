<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SystemSettingType;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemSetting>
 */
final class SystemSettingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = SystemSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $group = fake()->randomElement(['site', 'mail', 'social', 'api', 'general']);
        $name = fake()->words(2, true);
        $key = $group.'.'.str_replace(' ', '_', mb_strtolower($name));

        return [
            'group' => $group,
            'name' => ucwords($name),
            'key' => $key,
            'value' => fake()->sentence(),
            'type' => fake()->randomElement(SystemSettingType::cases())->value,
            'rules' => [],
            'remarks' => fake()->optional()->sentence(),
            'is_encrypted' => false,
            'is_public' => fake()->boolean(30), // 30% chance of being public
            'sort_order' => 0,
            'is_visible' => true,
        ];
    }

    /**
     * Indicate that the setting is encrypted.
     */
    public function encrypted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_encrypted' => true,
            'is_public' => false, // Encrypted settings should never be public
        ]);
    }

    /**
     * Indicate that the setting is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_public' => true,
            'is_encrypted' => false, // Public settings should never be encrypted
        ]);
    }

    /**
     * Indicate that the setting is private (not public).
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_public' => false,
        ]);
    }

    /**
     * Indicate that the setting is hidden.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_visible' => false,
        ]);
    }
}
