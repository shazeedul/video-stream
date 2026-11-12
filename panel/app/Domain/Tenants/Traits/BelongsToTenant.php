<?php

declare(strict_types=1);

namespace App\Domain\Tenants\Traits;

use App\Domain\Tenants\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the trait and apply the tenant global scope and auto-fill hook.
     */
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            if (app()->bound('current_tenant')) {
                /** @var mixed $tenant */
                $tenant = app('current_tenant');
                $tenantId = null;

                if ($tenant instanceof Tenant) {
                    $tenantId = $tenant->id;
                } elseif (is_object($tenant) && isset($tenant->id)) {
                    $tenantId = (string) $tenant->id;
                } elseif (is_string($tenant) && $tenant !== '') {
                    $tenantId = $tenant;
                }

                if ($tenantId !== null) {
                    $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
                }
            }
        });

        static::creating(function (Model $model): void {
            if (empty($model->getAttribute('tenant_id')) && app()->bound('current_tenant')) {
                /** @var mixed $tenant */
                $tenant = app('current_tenant');
                $tenantId = null;

                if ($tenant instanceof Tenant) {
                    $tenantId = $tenant->id;
                } elseif (is_object($tenant) && isset($tenant->id)) {
                    $tenantId = (string) $tenant->id;
                } elseif (is_string($tenant) && $tenant !== '') {
                    $tenantId = $tenant;
                }

                if ($tenantId !== null) {
                    $model->setAttribute('tenant_id', $tenantId);
                }
            }
        });
    }

    /**
     * Scope a query to ignore the tenant global scope.
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }

    /**
     * The tenant that this model belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
