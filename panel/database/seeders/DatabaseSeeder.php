<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

final class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Foundation - Roles, Permissions, and Core Settings
        $this->call([
            RolePermissionSeeder::class,
        ]);

        $this->call([
            UserSeeder::class,
        ]);

        // 3. Multi-Tenancy - Demo Organizations and Memberships
        $this->call([
            TenantSeeder::class,
        ]);

        // 4. System Settings
        $this->call([
            SystemSettingSeeder::class,
        ]);

        // Create storage link
        Artisan::call('storage:link', ['--force' => true]);

    }
}
