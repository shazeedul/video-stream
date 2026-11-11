<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Socialite\Contracts\User as SocialiteUser;

trait HasSocialAuth
{
    /**
     * Get all social accounts for this user.
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Check if user has a specific social provider.
     */
    public function hasProvider(string $provider): bool
    {
        return $this->socialAccounts()->where('provider', $provider)->exists();
    }

    /**
     * Get a specific social account for this user.
     */
    public function getProvider(string $provider): ?SocialAccount
    {
        return $this->socialAccounts()->where('provider', $provider)->first();
    }

    /**
     * Create or update a social account for this user.
     */
    public function createOrUpdateProvider(string $provider, SocialiteUser $socialiteUser, ?string $accessToken = null, ?string $refreshToken = null): SocialAccount
    {
        return $this->socialAccounts()->updateOrCreate(
            ['provider' => $provider],
            [
                'provider_id' => $socialiteUser->getId(),
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_at' => $this->calculateTokenExpiry($socialiteUser),
                'metadata' => $this->extractProviderMetadata($socialiteUser),
            ]
        );
    }

    /**
     * Check if user only has social authentication (no password).
     */
    public function isSocialOnly(): bool
    {
        return is_null($this->password) && $this->socialAccounts()->exists();
    }

    /**
     * Check if user can unlink a specific provider.
     */
    public function canUnlinkProvider(string $provider): bool
    {
        // Can't unlink if it's the only auth method and user has no password
        if ($this->isSocialOnly() && $this->socialAccounts()->count() === 1) {
            return false;
        }

        return $this->hasProvider($provider);
    }

    /**
     * Unlink a social provider.
     */
    public function unlinkProvider(string $provider): bool
    {
        if (! $this->canUnlinkProvider($provider)) {
            return false;
        }

        return $this->socialAccounts()->where('provider', $provider)->delete() > 0;
    }

    /**
     * Get all available social providers for this user.
     */
    public function getLinkedProviders(): array
    {
        return $this->socialAccounts()
            ->select('provider', 'metadata', 'created_at')
            ->get()
            ->map(fn (SocialAccount $socialAccount): array => [
                'provider' => $socialAccount->provider,
                'linked_at' => $socialAccount->created_at,
                'can_unlink' => $this->canUnlinkProvider($socialAccount->provider),
                'metadata' => $socialAccount->metadata,
            ])
            ->toArray();
    }

    /**
     * Update user profile from social provider if needed.
     */
    public function updateFromSocialProvider(SocialiteUser $socialiteUser): void
    {
        $updates = [];

        // Update email if not set
        if (empty($this->email) && $socialiteUser->getEmail()) {
            $updates['email'] = $socialiteUser->getEmail();
        }

        // Update name if not set
        if (empty($this->name) && $socialiteUser->getName()) {
            $updates['name'] = $socialiteUser->getName();
        }

        if (! empty($updates)) {
            $this->update($updates);
        }
    }

    /**
     * Check if user has a password set.
     */
    protected function getHasPasswordAttribute(): bool
    {
        return ! is_null($this->password);
    }

    /**
     * Calculate token expiry from socialite user data.
     */
    protected function calculateTokenExpiry(SocialiteUser $socialiteUser): ?string
    {
        // This depends on the provider and token data
        // Override in specific implementations if needed
        return null;
    }

    /**
     * Extract metadata from socialite user.
     */
    protected function extractProviderMetadata(SocialiteUser $socialiteUser): array
    {
        return [
            'avatar' => $socialiteUser->getAvatar(),
            'email' => $socialiteUser->getEmail(),
            'name' => $socialiteUser->getName(),
            'nickname' => $socialiteUser->getNickname(),
        ];
    }
}
