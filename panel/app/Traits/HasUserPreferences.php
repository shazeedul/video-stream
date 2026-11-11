<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Arr;

trait HasUserPreferences
{
    /**
     * Get default preferences structure.
     */
    public static function getDefaultPreferences(): array
    {
        return [
            'appearance' => 'light',
            'locale' => 'en',
            'notifications' => [
                'email' => true,
            ],
        ];
    }

    /**
     * Get all preferences.
     */
    public function getPreferences(): array
    {
        return $this->preferences ?? [];
    }

    /**
     * Get a specific preference value using dot notation.
     *
     * @param  string  $key  The preference key in dot notation (e.g., 'security.last_password_change')
     * @param  mixed  $default  Default value if preference doesn't exist
     */
    public function getPreference(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->getPreferences(), $key, $default);
    }

    /**
     * Set a specific preference value using dot notation.
     *
     * @param  string  $key  The preference key in dot notation
     * @param  mixed  $value  The value to set
     */
    public function setPreference(string $key, mixed $value): self
    {
        $preferences = $this->preferences ?? [];
        Arr::set($preferences, $key, $value);
        $this->preferences = $preferences;

        return $this;
    }

    /**
     * Set multiple preferences at once.
     *
     * @param  array  $preferences  Array of key-value pairs (supports dot notation)
     */
    public function setPreferences(array $preferences): self
    {
        $currentPrefs = $this->preferences ?? [];

        foreach ($preferences as $key => $value) {
            Arr::set($currentPrefs, $key, $value);
        }

        $this->preferences = $currentPrefs;

        return $this;
    }

    /**
     * Check if a preference exists.
     *
     * @param  string  $key  The preference key in dot notation
     */
    public function hasPreference(string $key): bool
    {
        return Arr::has($this->getPreferences(), $key);
    }

    /**
     * Initialize preferences with defaults if empty.
     */
    public function initializePreferences(): self
    {
        if (empty($this->preferences)) {
            $this->preferences = self::getDefaultPreferences();
        }

        return $this;
    }
}
