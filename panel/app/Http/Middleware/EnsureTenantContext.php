<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Tenants\Services\TenantContextService;
use App\Enums\TenantStatus;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantContext
{
    public function __construct(
        private readonly TenantContextService $tenantContextService,
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $tenant = $this->tenantContextService->getCurrentTenant($user);

            if ($tenant !== null) {
                if ($tenant->status->isSuspended()) {
                    abort(403, __('Your organization has been suspended. Please contact support.'));
                }

                app()->instance('current_tenant', $tenant);

                Inertia::share('currentTenant', fn () => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'status' => $tenant->status instanceof TenantStatus ? $tenant->status->value : (string) $tenant->status,
                    'role' => $request->user()?->tenantRole($tenant)?->value,
                    'settings' => $tenant->settings,
                ]);
            }
        }

        return $next($request);
    }
}
