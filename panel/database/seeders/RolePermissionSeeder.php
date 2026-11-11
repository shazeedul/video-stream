<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\RolePermissionSyncService;
use Illuminate\Database\Seeder;

/**
 * Role and Permission Seeder
 *
 * This seeder uses the RolePermissionSyncService to sync roles and permissions
 * from the definition files in app/Support/Authorization/.
 *
 * For fresh migrations: php artisan db:seed --class=RolePermissionSeeder
 * For production sync: php artisan auth:permissions-sync
 */
final class RolePermissionSeeder extends Seeder
{
    public function __construct(
        private readonly RolePermissionSyncService $aclSyncService
    ) {}

    public function run(): void
    {
        $this->aclSyncService->sync();
    }
}
