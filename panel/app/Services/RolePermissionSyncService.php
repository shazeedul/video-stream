<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Authorization\RolePermissions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role and Permission Synchronization Service
 *
 * Handles synchronization of roles and permissions between definition files and database.
 *
 * Key behaviors:
 * - Only syncs default roles (is_default=true), custom roles are untouched
 * - Deletes orphaned permissions and their role assignments
 * - Creates new permissions from definition files
 * - Updates default role permission assignments
 * - Preserves permission IDs for existing permissions
 */
final class RolePermissionSyncService
{
    private bool $dryRun = false;

    /**
     * @var array<string, int>
     */
    private array $stats = [
        'roles_created' => 0,
        'roles_updated' => 0,
        'roles_deleted' => 0,
        'permissions_created' => 0,
        'permissions_updated' => 0,
        'permissions_deleted' => 0,
        'role_assignments_synced' => 0,
    ];

    /**
     * @var array<string>
     */
    private array $logs = [];

    public function __construct(
        private readonly PermissionRegistrar $registrar
    ) {}

    /**
     * Enable dry run mode (no database changes)
     */
    public function dryRun(bool $enabled = true): self
    {
        $this->dryRun = $enabled;

        return $this;
    }

    /**
     * Sync all roles and permissions from definition files to database.
     *
     * @return array{stats: array<string, int>, logs: array<string>}
     */
    public function sync(): array
    {
        $this->resetState();

        // Clear permission cache before sync
        $this->registrar->forgetCachedPermissions();

        DB::transaction(function (): void {
            // 1. Sync roles first (admin + user)
            $this->syncRoles();

            // 2. Sync permissions (admin + user)
            $this->syncPermissions();
        });

        // Clear permission cache after sync
        $this->registrar->forgetCachedPermissions();

        return [
            'stats' => $this->stats,
            'logs' => $this->logs,
        ];
    }

    /**
     * Get all role definitions from both admin and user files.
     *
     * @return array<int, array{name: string, display_name: string, description: string, is_default: bool, guard_name: string}>
     */
    public function getAllRoleDefinitions(): array
    {
        return array_merge(
            RolePermissions::roles()
        );
    }

    /**
     * Get all permission definitions from both admin and user files.
     * Properly merges groups with the same name by combining their permissions.
     *
     * @return array<string, array<int, array{name: string, display_name: string,  roles: array<string>}>>
     */
    public function getAllPermissionDefinitions(): array
    {
        return RolePermissions::permissions();
    }

    /**
     * Get flattened permission list from definitions.
     *
     * @return Collection<int, array{name: string, display_name: string, roles: array<string>, group: string}>
     */
    public function getFlattenedPermissions(): Collection
    {
        $permissions = collect();

        foreach ($this->getAllPermissionDefinitions() as $group => $groupPermissions) {
            foreach ($groupPermissions as $permission) {
                $permissions->push(array_merge($permission, ['group' => $group]));
            }
        }

        return $permissions;
    }

    /**
     * Get sync statistics.
     *
     * @return array<string, int>
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Get sync logs.
     *
     * @return array<string>
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * Sync roles from definition files to database.
     */
    private function syncRoles(): void
    {
        $roleDefinitions = $this->getAllRoleDefinitions();
        $definedRoleNames = collect($roleDefinitions)->pluck('name')->toArray();

        // 1. Create or update roles from definitions
        foreach ($roleDefinitions as $roleData) {
            $existingRole = Role::query()
                ->where('name', $roleData['name'])
                ->first();

            if ($existingRole) {
                if (! $this->dryRun) {
                    $existingRole->update($roleData);
                }

                $this->log('Updated role: '.$roleData['name']);
                $this->stats['roles_updated']++;
            } else {
                if (! $this->dryRun) {
                    Role::query()->create($roleData);
                }

                $this->log('Created role: '.$roleData['name']);
                $this->stats['roles_created']++;
            }
        }

        // 2. Delete orphaned default roles (only is_default=true
        $orphanedRoles = Role::query()
            ->whereNotIn('name', $definedRoleNames)
            ->where('is_default', true)
            ->get();

        foreach ($orphanedRoles as $role) {
            $this->log('Deleting orphaned role: '.$role->name);

            if (! $this->dryRun) {
                // This will also detach all permissions via Spatie
                $role->delete();
            }

            $this->stats['roles_deleted']++;
        }
    }

    /**
     * Sync permissions from definition files to database.
     */
    private function syncPermissions(): void
    {
        $flattenedPermissions = $this->getFlattenedPermissions();
        $definedPermissionNames = $flattenedPermissions->pluck('name')->toArray();

        // 1. Get all existing permissions from database
        $existingPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->get()
            ->keyBy('name');

        // 2. Create or update permissions from definitions
        foreach ($flattenedPermissions as $permData) {
            $this->syncSinglePermission($permData, $existingPermissions);
        }

        // 3. Delete orphaned permissions
        $this->deleteOrphanedPermissions($definedPermissionNames);
    }

    /**
     * Sync a single permission.
     */
    private function syncSinglePermission(array $permData, Collection $existingPermissions): void
    {
        $existingPermission = $existingPermissions->get($permData['name']);

        if ($existingPermission) {
            // Update existing permission
            if (! $this->dryRun) {
                $existingPermission->update([
                    'display_name' => $permData['display_name'],
                    'group' => $permData['group'],
                ]);
            }

            $this->log('Updated permission: '.$permData['name']);
            $this->stats['permissions_updated']++;
        } else {
            // Create new permission
            if (! $this->dryRun) {
                $existingPermission = Permission::query()->create([
                    'name' => $permData['name'],
                    'display_name' => $permData['display_name'],
                    'group' => $permData['group'],
                    'guard_name' => 'web',
                ]);
            }

            $this->log('Created permission: '.$permData['name']);
            $this->stats['permissions_created']++;
        }

        // Sync role assignments for default roles only
        if (! $this->dryRun && $existingPermission) {
            $this->syncPermissionRoles($existingPermission, $permData['roles']);
        }
    }

    /**
     * Delete permissions that are no longer defined.
     */
    private function deleteOrphanedPermissions(array $definedPermissionNames): void
    {
        $orphanedPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', $definedPermissionNames)
            ->get();

        foreach ($orphanedPermissions as $permission) {
            $this->log('Deleting orphaned permission: '.$permission->name);

            if (! $this->dryRun) {
                // Delete role_has_permissions records
                DB::table('role_has_permissions')
                    ->where('permission_id', $permission->id)
                    ->delete();

                // Delete model_has_permissions records
                DB::table('model_has_permissions')
                    ->where('permission_id', $permission->id)
                    ->delete();

                // Delete the permission
                $permission->delete();
            }

            $this->stats['permissions_deleted']++;
        }
    }

    /**
     * Sync role assignments for a permission.
     * Only syncs default roles, preserves custom role assignments.
     *
     * @param  array<string>  $definedRoleNames
     */
    private function syncPermissionRoles(Permission $permission, array $definedRoleNames): void
    {
        // Get all default roles (is_default=true)
        $defaultRoles = Role::query()
            ->where('is_default', true)
            ->get();

        $defaultRoleIds = $defaultRoles->pluck('id')->toArray();

        // Get current role assignments for this permission (only default roles)
        $currentDefaultRoleIds = DB::table('role_has_permissions')
            ->where('permission_id', $permission->id)
            ->whereIn('role_id', $defaultRoleIds)
            ->pluck('role_id')
            ->toArray();

        // Get role IDs that should have this permission
        $targetRoleIds = $defaultRoles
            ->whereIn('name', $definedRoleNames)
            ->pluck('id')
            ->toArray();

        // Calculate differences
        $rolesToAdd = array_diff($targetRoleIds, $currentDefaultRoleIds);
        $rolesToRemove = array_diff($currentDefaultRoleIds, $targetRoleIds);

        // Add missing role assignments
        foreach ($rolesToAdd as $roleId) {
            DB::table('role_has_permissions')->insert([
                'permission_id' => $permission->id,
                'role_id' => $roleId,
            ]);
            $role = $defaultRoles->firstWhere('id', $roleId);
            $this->log('  + Assigned to role: '.$role?->name);
        }

        // Remove extra role assignments
        foreach ($rolesToRemove as $roleId) {
            DB::table('role_has_permissions')
                ->where('permission_id', $permission->id)
                ->where('role_id', $roleId)
                ->delete();
            $role = $defaultRoles->firstWhere('id', $roleId);
            $this->log('  - Removed from role: '.$role?->name);
        }

        if ($rolesToAdd !== [] || $rolesToRemove !== []) {
            $this->stats['role_assignments_synced']++;
        }
    }

    /**
     * Reset internal state for a new sync operation.
     */
    private function resetState(): void
    {
        $this->stats = [
            'roles_created' => 0,
            'roles_updated' => 0,
            'roles_deleted' => 0,
            'permissions_created' => 0,
            'permissions_updated' => 0,
            'permissions_deleted' => 0,
            'role_assignments_synced' => 0,
        ];
        $this->logs = [];
    }

    /**
     * Add a log entry.
     */
    private function log(string $message): void
    {
        $this->logs[] = $message;
    }
}
