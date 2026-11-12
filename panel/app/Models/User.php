<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Models\TenantUser;
use App\Domain\Videos\Models\Video;
use App\Enums\TenantRole;
use App\Enums\TenantUserStatus;
use App\Enums\UserStatus;
use App\Traits\HasProfilePhoto;
use App\Traits\HasSocialAuth;
use App\Traits\HasUserPreferences;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

#[\Illuminate\Database\Eloquent\Attributes\Appends(['profile_photo_url', 'has_password'])]
#[\Illuminate\Database\Eloquent\Attributes\Fillable([
    'name',
    'username',
    'email',
    'password',
    'phone',
    'bio',
    'company',
    'designation',
    'address',
    'city',
    'state',
    'zip_code',
    'social_links',
    'preferences',
    'status',
    'status_reason',
    'profile_photo_path',
    'scheduled_deletion_at',
])]
#[\Illuminate\Database\Eloquent\Attributes\Hidden([
    'password',
    'remember_token',
])]
final class User extends Authenticatable
{
    use HasFactory;
    use HasProfilePhoto;
    use HasRoles;
    use HasSocialAuth;
    use HasUserPreferences;
    use Notifiable;
    use SoftDeletes;

    /**
     * Get the social accounts for this user.
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Get the tenants that the user belongs to.
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->using(TenantUser::class)
            ->withPivot(['id', 'role', 'status'])
            ->withTimestamps();
    }

    /**
     * Get the tenant memberships for the user.
     */
    public function tenantMemberships(): HasMany
    {
        return $this->hasMany(TenantUser::class, 'user_id');
    }

    /**
     * Get the videos created by the user.
     */
    public function videos(): HasMany
    {
        return $this->hasMany(Video::class, 'user_id');
    }

    /**
     * Check if the user is an owner of the given tenant.
     */
    public function isTenantOwner(Tenant $tenant): bool
    {
        return $this->tenantRole($tenant)?->isOwner() ?? false;
    }

    /**
     * Check if the user is a member of the given tenant.
     */
    public function isTenantMember(Tenant $tenant): bool
    {
        return $this->tenantRole($tenant) !== null;
    }

    /**
     * Get the user's role in the given tenant.
     */
    public function tenantRole(Tenant $tenant): ?TenantRole
    {
        if ($this->relationLoaded('tenants')) {
            $loadedTenant = $this->tenants->firstWhere('id', $tenant->id);
            if ($loadedTenant !== null && $loadedTenant->pivot !== null) {
                $status = $loadedTenant->pivot->status;
                $statusEnum = $status instanceof TenantUserStatus ? $status : (is_string($status) ? TenantUserStatus::tryFrom($status) : null);
                if ($statusEnum !== TenantUserStatus::ACTIVE) {
                    return null;
                }

                $role = $loadedTenant->pivot->role;

                return $role instanceof TenantRole ? $role : (is_string($role) ? TenantRole::tryFrom($role) : null);
            }
        }

        if ($this->relationLoaded('tenantMemberships')) {
            $membership = $this->tenantMemberships->firstWhere('tenant_id', $tenant->id);
            if ($membership !== null) {
                $status = $membership->status;
                $statusEnum = $status instanceof TenantUserStatus ? $status : (is_string($status) ? TenantUserStatus::tryFrom($status) : null);
                if ($statusEnum !== TenantUserStatus::ACTIVE) {
                    return null;
                }

                $role = $membership->role;

                return $role instanceof TenantRole ? $role : (is_string($role) ? TenantRole::tryFrom($role) : null);
            }
        }

        $membership = $this->tenantMemberships()->where('tenant_id', $tenant->id)->first();
        if ($membership !== null) {
            $status = $membership->status;
            $statusEnum = $status instanceof TenantUserStatus ? $status : (is_string($status) ? TenantUserStatus::tryFrom($status) : null);
            if ($statusEnum !== TenantUserStatus::ACTIVE) {
                return null;
            }

            $role = $membership->role;

            return $role instanceof TenantRole ? $role : (is_string($role) ? TenantRole::tryFrom($role) : null);
        }

        return null;
    }

    /**
     * Check if user is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === UserStatus::SUSPENDED;
    }

    /**
     * Check if user is active.
     */
    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    /**
     * Suspend the user with a reason.
     */
    public function suspend(string $reason): void
    {
        $this->status = UserStatus::SUSPENDED;
        $this->status_reason = $reason;
        $this->status_updated_at = now();
        $this->save();
    }

    /**
     * Unsuspend the user.
     */
    public function unsuspend(): void
    {
        $this->status = UserStatus::ACTIVE;
        $this->status_reason = null;
        $this->status_updated_at = now();
        $this->save();
    }

    /**
     * Boot the model and generate a unique username when creating.
     */
    protected static function booted(): void
    {
        self::creating(function (User $user): void {
            if (! empty($user->username)) {
                return;
            }

            // Base candidate from name, fallback to email local part, fallback to "user"
            $source = $user->name ?? $user->email ?? 'user';
            if (empty($source) && ! empty($user->email)) {
                $source = Str::before($user->email, '@');
            }

            // slugify and constrain length
            $base = Str::of($source)->ascii()->lower()->slug('-')->substr(0, 32)->toString();
            $base = $base === '' ? 'user' : $base;

            $candidate = $base;
            $suffix = 1;

            // Ensure uniqueness by appending -1, -2, ... when needed
            while (User::query()->where('username', $candidate)->exists()) {
                $candidate = $base.'-'.$suffix++;
            }

            $user->username = $candidate;
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'status_updated_at' => 'datetime',
            'scheduled_deletion_at' => 'datetime',
            'social_links' => 'array',
            'preferences' => 'array',
        ];
    }

    /**
     * Get users with verified emails.
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function verified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Scope to get only active users.
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function activeStatus($query)
    {
        return $query->where('status', UserStatus::ACTIVE);
    }

    /**
     * Scope to get only suspended users.
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function suspended($query)
    {
        return $query->where('status', UserStatus::SUSPENDED);
    }
}
