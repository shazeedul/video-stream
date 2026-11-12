<?php

declare(strict_types=1);

namespace App\Domain\Tenants\Models;

use App\Domain\Uploads\Models\UploadSession;
use App\Domain\Videos\Models\Video;
use App\Enums\TenantStatus;
use App\Models\User;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'slug',
    'status',
    'settings',
])]
final class Tenant extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'settings',
    ];

    /**
     * Users that belong to this tenant.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->using(TenantUser::class)
            ->withPivot(['id', 'role', 'status'])
            ->withTimestamps();
    }

    /**
     * Pivot memberships belonging to this tenant.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TenantUser::class, 'tenant_id');
    }

    /**
     * Videos belonging to this tenant.
     */
    public function videos(): HasMany
    {
        return $this->hasMany(Video::class, 'tenant_id');
    }

    /**
     * Upload sessions belonging to this tenant.
     */
    public function uploadSessions(): HasMany
    {
        return $this->hasMany(UploadSession::class, 'tenant_id');
    }

    /**
     * Scope to active tenants.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', TenantStatus::ACTIVE);
    }

    /**
     * Scope to search tenants by name or slug.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || mb_trim($term) === '') {
            return $query;
        }

        $term = mb_trim($term);

        return $query->where(function (Builder $builder) use ($term): void {
            $builder->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($term).'%'])
                ->orWhereRaw('LOWER(slug) LIKE ?', ['%'.mb_strtolower($term).'%']);
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'settings' => 'array',
        ];
    }
}
