<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal\Settings;

use App\Domain\Tenants\Contracts\TenantContextServiceInterface;
use App\Domain\Tenants\Models\TenantUser;
use App\Enums\TenantRole;
use App\Enums\TenantUserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreMemberRequest;
use App\Http\Requests\Portal\UpdateMemberRoleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class OrganizationMemberController extends Controller
{
    /**
     * List all members of the active organization.
     */
    public function index(Request $request, TenantContextServiceInterface $contextService): Response
    {
        $currentTenant = $contextService->getActiveTenant($request->user());

        if ($currentTenant === null) {
            abort(404, __('Organization not found.'));
        }

        $user = $request->user();

        if ($user === null || $user->cannot('manageMembers', $currentTenant)) {
            abort(403);
        }

        $members = $currentTenant->memberships()
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (TenantUser $membership) => [
                'id' => $membership->id,
                'user_id' => $membership->user_id,
                'role' => $membership->role instanceof TenantRole ? $membership->role->value : (string) $membership->role,
                'role_label' => $membership->role instanceof TenantRole ? $membership->role->label() : (string) $membership->role,
                'status' => $membership->status instanceof TenantUserStatus ? $membership->status->value : (string) $membership->status,
                'status_label' => $membership->status instanceof TenantUserStatus ? $membership->status->label() : (string) $membership->status,
                'created_at' => $membership->created_at?->toIso8601String(),
                'user' => [
                    'id' => $membership->user?->id,
                    'name' => $membership->user?->name,
                    'email' => $membership->user?->email,
                    'profile_photo_url' => $membership->user?->profile_photo_url,
                ],
            ]);

        return Inertia::render('portal/settings/organization/members', [
            'members' => $members,
            'roles' => collect(TenantRole::cases())->map(fn (TenantRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
            ]),
        ]);
    }

    /**
     * Add an existing user as a member to the active organization.
     */
    public function store(StoreMemberRequest $request): RedirectResponse
    {
        $currentTenant = $request->currentTenant();

        if ($currentTenant === null) {
            abort(404, __('Organization not found.'));
        }

        $user = $request->user();

        if ($user === null || $user->cannot('manageMembers', $currentTenant)) {
            abort(403);
        }

        /** @var string $email */
        $email = $request->validated('email');
        /** @var string $role */
        $role = $request->validated('role');

        if ($role === TenantRole::OWNER->value && ! $user->isTenantOwner($currentTenant) && ! $user->can('tenants.update')) {
            abort(403, __('Only organization owners can manage owner accounts.'));
        }

        $targetUser = User::query()->where('email', $email)->firstOrFail();

        $currentTenant->users()->attach($targetUser->id, [
            'id' => (string) Str::uuid(),
            'role' => $role,
            'status' => TenantUserStatus::ACTIVE->value,
        ]);

        return back()->with('success', __('Member added successfully.'));
    }

    /**
     * Update an organization member's role.
     */
    public function update(UpdateMemberRoleRequest $request, TenantUser $membership): RedirectResponse
    {
        $currentTenant = $request->currentTenant();

        if ($currentTenant === null) {
            abort(404, __('Organization not found.'));
        }

        $user = $request->user();

        if ($user === null || $user->cannot('manageMembers', $currentTenant)) {
            abort(403);
        }

        if ($membership->tenant_id !== $currentTenant->id) {
            abort(404, __('Member not found.'));
        }

        /** @var string $newRole */
        $newRole = $request->validated('role');

        $isOwner = $membership->role instanceof TenantRole
            ? $membership->role->isOwner()
            : $membership->role === TenantRole::OWNER->value;

        $isPromotingToOwner = $newRole === TenantRole::OWNER->value;

        if (($isOwner || $isPromotingToOwner) && ! $user->isTenantOwner($currentTenant) && ! $user->can('tenants.update')) {
            abort(403, __('Only organization owners can manage owner accounts.'));
        }

        if ($isOwner && $newRole !== TenantRole::OWNER->value) {
            $otherOwnersCount = $currentTenant->memberships()
                ->where('role', TenantRole::OWNER)
                ->where('id', '!=', $membership->id)
                ->count();

            if ($otherOwnersCount === 0) {
                abort(422, __('An organization must have at least one active owner.'));
            }
        }

        $membership->update(['role' => $newRole]);

        return back()->with('success', __('Member role updated successfully.'));
    }

    /**
     * Remove a member from the active organization.
     */
    public function destroy(
        Request $request,
        TenantUser $membership,
        TenantContextServiceInterface $contextService
    ): RedirectResponse {
        $currentTenant = $contextService->getActiveTenant($request->user());

        if ($currentTenant === null) {
            abort(404, __('Organization not found.'));
        }

        $user = $request->user();

        if ($user === null || $user->cannot('manageMembers', $currentTenant)) {
            abort(403);
        }

        if ($membership->tenant_id !== $currentTenant->id) {
            abort(404, __('Member not found.'));
        }

        $isOwner = $membership->role instanceof TenantRole
            ? $membership->role->isOwner()
            : $membership->role === TenantRole::OWNER->value;

        if ($isOwner) {
            if (! $user->isTenantOwner($currentTenant) && ! $user->can('tenants.update')) {
                abort(403, __('Only organization owners can manage owner accounts.'));
            }

            $otherOwnersCount = $currentTenant->memberships()
                ->where('role', TenantRole::OWNER)
                ->where('id', '!=', $membership->id)
                ->count();

            if ($otherOwnersCount === 0) {
                abort(422, __('Cannot remove the only owner of the organization.'));
            }
        }

        $membership->delete();

        return back()->with('success', __('Member removed successfully.'));
    }
}
