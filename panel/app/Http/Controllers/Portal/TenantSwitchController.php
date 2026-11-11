<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Tenants\Contracts\TenantContextServiceInterface;
use App\Domain\Tenants\Models\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TenantSwitchController extends Controller
{
    /**
     * Switch the active organization context.
     */
    public function switch(Request $request, Tenant $tenant, TenantContextServiceInterface $contextService): RedirectResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->isTenantMember($tenant)) {
            abort(403, __('You are not a member of this organization.'));
        }

        $switched = $contextService->setCurrentTenant($user, $tenant);

        if (! $switched) {
            return back()->with('error', __('Failed to switch active organization.'));
        }

        return back()->with('success', __('Switched active organization.'));
    }
}
