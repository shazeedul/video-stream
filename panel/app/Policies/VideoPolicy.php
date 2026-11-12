<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\Video;
use App\Models\User;

final class VideoPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($ability === 'viewAny' && $user->can('videos.view')) {
            return true;
        }

        if ($user->can('videos.'.$ability)) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any videos.
     */
    public function viewAny(User $user): bool
    {
        if ($user->can('videos.view')) {
            return true;
        }

        $tenant = $this->resolveCurrentTenant();

        return $tenant !== null && $user->isTenantMember($tenant);
    }

    /**
     * Determine whether the user can view the video.
     */
    public function view(User $user, Video $video): bool
    {
        if ($user->can('videos.view')) {
            return true;
        }

        $tenant = $this->resolveVideoTenant($video);

        return $tenant !== null && $user->isTenantMember($tenant);
    }

    /**
     * Determine whether the user can create videos.
     */
    public function create(User $user): bool
    {
        if ($user->can('videos.create')) {
            return true;
        }

        $tenant = $this->resolveCurrentTenant();
        if ($tenant === null) {
            return false;
        }

        $role = $user->tenantRole($tenant);
        if ($role === null || $role->isViewer()) {
            return false;
        }

        return $role->canManageVideos();
    }

    /**
     * Determine whether the user can update the video.
     */
    public function update(User $user, Video $video): bool
    {
        if ($user->can('videos.update')) {
            return true;
        }

        $tenant = $this->resolveVideoTenant($video);
        if ($tenant === null) {
            return false;
        }

        $role = $user->tenantRole($tenant);
        if ($role === null) {
            return false;
        }

        if ($role->isOwner() || $role->isAdmin()) {
            return true;
        }

        if ($role->isMember() && $video->user_id !== null && (string) $video->user_id === (string) $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the video.
     */
    public function delete(User $user, Video $video): bool
    {
        if ($user->can('videos.delete')) {
            return true;
        }

        $tenant = $this->resolveVideoTenant($video);
        if ($tenant === null) {
            return false;
        }

        $role = $user->tenantRole($tenant);
        if ($role === null) {
            return false;
        }

        return $role->isOwner() || $role->isAdmin();
    }

    /**
     * Determine whether the user can restore the video.
     */
    public function restore(User $user, Video $video): bool
    {
        return $this->delete($user, $video);
    }

    /**
     * Determine whether the user can permanently delete the video.
     */
    public function forceDelete(User $user, Video $video): bool
    {
        return $this->delete($user, $video);
    }

    /**
     * Resolve the active tenant context.
     */
    private function resolveCurrentTenant(): ?Tenant
    {
        if (! app()->bound('current_tenant')) {
            return null;
        }

        $tenant = app('current_tenant');

        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        if (is_object($tenant) && isset($tenant->id)) {
            return Tenant::query()->find($tenant->id);
        }

        if (is_string($tenant) && $tenant !== '') {
            return Tenant::query()->find($tenant);
        }

        return null;
    }

    /**
     * Resolve the tenant for a given video.
     */
    private function resolveVideoTenant(Video $video): ?Tenant
    {
        if ($video->relationLoaded('tenant')) {
            return $video->tenant;
        }

        $tenant = $video->tenant;
        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        if ($video->tenant_id !== null) {
            return Tenant::query()->find($video->tenant_id);
        }

        return null;
    }
}
