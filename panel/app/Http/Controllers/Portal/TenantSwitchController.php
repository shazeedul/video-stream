<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Services\TenantContextService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TenantSwitchController extends Controller
{
    /**
     * Switch the active organization context.
     */
    public function switch(Request $request, Tenant $tenant, TenantContextService $contextService): RedirectResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->isTenantMember($tenant)) {
            abort(403, __('You are not a member of this organization.'));
        }

        $contextService->setCurrentTenant($user, $tenant);

        return back()->with('success', __('Switched active organization.'));
    }
}
