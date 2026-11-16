<?php

declare(strict_types=1);

namespace App\Domain\Usage\Models;

use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tenant_id',
    'date',
    'storage_bytes',
    'egress_bytes',
    'view_count',
    'video_count',
])]
final class TenantUsageRecord extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'date',
        'storage_bytes',
        'egress_bytes',
        'view_count',
        'video_count',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'storage_bytes' => 'integer',
            'egress_bytes' => 'integer',
            'view_count' => 'integer',
            'video_count' => 'integer',
        ];
    }
}
