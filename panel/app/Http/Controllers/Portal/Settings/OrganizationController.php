<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal\Settings;

use App\Domain\Tenants\Services\TenantContextService;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\UpdateOrganizationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OrganizationController extends Controller
{
    /**
     * Show the organization overview / settings page.
     */
    public function overview(Request $request, TenantContextService $contextService): Response
    {
        $currentTenant = $contextService->getActiveTenant($request->user());

        if ($currentTenant === null) {
            abort(404, __('Organization not found.'));
        }

        $user = $request->user();

        if ($user === null || $user->cannot('view', $currentTenant)) {
            abort(403);
        }

        return Inertia::render('portal/settings/organization/overview', [
            'organization' => [
                'id' => $currentTenant->id,
                'name' => $currentTenant->name,
                'slug' => $currentTenant->slug,
                'status' => $currentTenant->status instanceof TenantStatus ? $currentTenant->status->value : (string) $currentTenant->status,
                'settings' => $currentTenant->settings ?? [],
                'created_at' => $currentTenant->created_at?->toIso8601String(),
            ],
            'userRole' => $user->tenantRole($currentTenant)?->value,
        ]);
    }

    /**
     * Update the organization profile settings.
     */
    public function update(UpdateOrganizationRequest $request): RedirectResponse
    {
        $currentTenant = $request->currentTenant();

        if ($currentTenant === null) {
            abort(404, __('Organization not found.'));
        }

        $user = $request->user();

        if ($user === null || $user->cannot('update', $currentTenant)) {
            abort(403);
        }

        $currentTenant->update($request->validated());

        return back()->with('success', __('Organization updated successfully.'));
    }
}
