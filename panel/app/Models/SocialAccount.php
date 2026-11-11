<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[\Illuminate\Database\Eloquent\Attributes\Fillable([
    'user_id',
    'provider',
    'provider_id',
    'access_token',
    'refresh_token',
    'metadata',
    'expires_at',
])]
final class SocialAccount extends Model
{
    use HasFactory;

    /**
     * Get the user that owns the auth provider.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the access token is expired.
     */
    public function isTokenExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the avatar URL from metadata.
     */
    protected function getAvatarAttribute(): ?string
    {
        return $this->metadata['avatar'] ?? null;
    }

    /**
     * Get the email from metadata.
     */
    protected function getEmailAttribute(): ?string
    {
        return $this->metadata['email'] ?? null;
    }

    /**
     * Get the name from metadata.
     */
    protected function getNameAttribute(): ?string
    {
        return $this->metadata['name'] ?? null;
    }

    /**
     * Get the username from metadata.
     */
    protected function getUsernameAttribute(): ?string
    {
        return $this->metadata['username'] ?? $this->metadata['nickname'] ?? null;
    }
}
