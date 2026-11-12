<?php

declare(strict_types=1);

namespace App\Support\Authorization;

/**
 * Roles and permissions definition.
 *
 * This is the source of truth for all roles and permissions.
 * The ACL sync command will use this file to sync the database with these definitions.
 *
 * IMPORTANT:
 * - Adding new permissions: Just add them here and run `php artisan auth:permissions-sync`
 * - Removing permissions: Remove from here and run `php artisan auth:permissions-sync` - the command will delete orphaned permissions
 * - Modifying permissions: Update here and run `php artisan auth:permissions-sync` - default role assignments will be synced
 * - Custom roles (is_default=false) are NOT affected by sync
 */
final class RolePermissions
{
    /**
     * Get all admin/global roles for the application.
     *
     * @return array<int, array{name: string, display_name: string, description: string, is_default: bool, guard_name: string}>
     */
    public static function roles(): array
    {
        return [
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Platform administrator with operational control over content, users, and business functions. Cannot access sensitive system operations.',
                'is_default' => true,
                'guard_name' => 'web',
            ],
            [
                'name' => 'user',
                'display_name' => 'User',
                'description' => 'Regular user with access to basic features.',
                'is_default' => true,
                'guard_name' => 'web',
            ],
        ];
    }

    /**
     * Get all admin-related permissions grouped by functionality.
     *
     * Each permission contains:
     * - name: Unique permission identifier
     * - display_name: Human-readable name
     * - roles: Array of default role names that should have this permission
     *
     * @return array<string, array<int, array{name: string, display_name: string, roles: array<string>}>>
     */
    public static function permissions(): array
    {
        return [
            // Admin Panel Access
            'Admin Panel Access' => [
                [
                    'name' => 'admin.panel.view',
                    'display_name' => 'Admin Panel Access',
                    'roles' => ['admin'],
                ],
            ],
            // Tenants
            'Tenants' => [
                [
                    'name' => 'tenants.view',
                    'display_name' => 'View Tenants',
                    'roles' => ['admin'],
                ],
                [
                    'name' => 'tenants.create',
                    'display_name' => 'Create Tenants',
                    'roles' => ['admin'],
                ],
                [
                    'name' => 'tenants.update',
                    'display_name' => 'Update Tenants',
                    'roles' => ['admin'],
                ],
                [
                    'name' => 'tenants.suspend',
                    'display_name' => 'Suspend Tenants',
                    'roles' => ['admin'],
                ],
                [
                    'name' => 'tenants.delete',
                    'display_name' => 'Delete Tenants',
                    'roles' => ['admin'],
                ],
            ],
            // Videos
            'Videos' => [
                [
                    'name' => 'videos.view',
                    'display_name' => 'View Videos',
                    'roles' => ['admin'],
                ],
                [
                    'name' => 'videos.create',
                    'display_name' => 'Create Videos',
                    'roles' => ['admin'],
                ],
                [
                    'name' => 'videos.update',
                    'display_name' => 'Update Videos',
                    'roles' => ['admin'],
                ],
                [
                    'name' => 'videos.delete',
                    'display_name' => 'Delete Videos',
                    'roles' => ['admin'],
                ],
            ],
            'Users' => [
                [
                    'name' => 'users.view',
                    'display_name' => 'View Users',
                    'roles' => ['admin'],
                ],
                [
                    'name' => 'users.create',
                    'display_name' => 'Create Users',
                    'roles' => ['admin'],
                ],
                [
                    'name' => 'users.update',
                    'display_name' => 'Update Users',
                    'roles' => ['admin'],
                ],
                [
                    'name' => 'users.delete',
                    'display_name' => 'Delete Users',
                    'roles' => ['admin'],
                ],
            ],
            'Roles' => [
                [
                    'name' => 'roles.view',
                    'display_name' => 'View Roles',
                    'roles' => ['admin'],
                ],
                [
                    'name' => 'roles.create',
                    'display_name' => 'Create Roles',
                    'roles' => ['admin'],
                ],
                [
                    'name' => 'roles.update',
                    'display_name' => 'Update Roles',
                    'roles' => ['admin'],
                ],
                [
                    'name' => 'roles.delete',
                    'display_name' => 'Delete Roles',
                    'roles' => ['admin'],
                ],
            ],
            'System Settings' => [
                [
                    'name' => 'system-settings.view',
                    'display_name' => 'View Settings',
                    'roles' => ['admin'],
                ],
                [
                    'name' => 'system-settings.create',
                    'display_name' => 'Create Settings',
                    'roles' => ['admin'],
                ],
                [
                    'name' => 'system-settings.edit',
                    'display_name' => 'Edit Settings',
                    'roles' => ['admin'],
                ],
                [
                    'name' => 'system-settings.update',
                    'display_name' => 'Update Settings',
                    'roles' => ['admin'],
                ],
                [
                    'name' => 'system-settings.delete',
                    'display_name' => 'Delete Settings',
                    'roles' => ['admin'],
                ],
            ],
            // DevTools
            'DevTools' => [
                [
                    'name' => 'admin.devtools.horizon',
                    'display_name' => 'Access Horizon Dashboard',
                    'roles' => ['admin'],
                ],
            ],
            // Settings & Profile
            'Settings & Profile' => [
                [
                    'name' => 'profile.view',
                    'display_name' => 'View User Profile',
                    'roles' => ['admin', 'user'],
                ],
                [
                    'name' => 'profile.edit',
                    'display_name' => 'Edit User Profile',
                    'roles' => ['admin', 'user'],
                ],
                [
                    'name' => 'profile.delete',
                    'display_name' => 'Delete User Account',
                    'roles' => ['admin', 'user'],
                ],
                [
                    'name' => 'profile.password.change',
                    'display_name' => 'Change Password',
                    'roles' => ['admin', 'user'],
                ],
                [
                    'name' => 'profile.preferences',
                    'display_name' => 'Manage Preferences',
                    'roles' => ['admin', 'user'],
                ],
            ],
        ];
    }
}
