<?php

declare(strict_types=1);

namespace App\Domain\Tenants\Models;

use App\Enums\TenantRole;
use App\Enums\TenantUserStatus;
use App\Models\User;
use Database\Factories\TenantUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string $id
 * @property string $tenant_id
 * @property int $user_id
 * @property TenantRole $role
 * @property TenantUserStatus $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read User|null $user
 */
#[Fillable([
    'id',
    'tenant_id',
    'user_id',
    'role',
    'status',
])]
final class TenantUser extends Pivot
{
    use HasFactory;
    use HasUuids;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tenant_user';

    protected $fillable = [
        'id',
        'tenant_id',
        'user_id',
        'role',
        'status',
    ];

    /**
     * The tenant that this membership belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * The user that this membership belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): TenantUserFactory
    {
        return TenantUserFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => TenantRole::class,
            'status' => TenantUserStatus::class,
        ];
    }
}
