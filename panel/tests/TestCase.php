<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * @property \App\Models\User $admin
 * @property \App\Models\User $user
 * @property \App\Models\User $technician
 * @property \App\Models\User $otherTechnician
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Creates the application.
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected $admin;
    protected $user;
    protected $technician;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear permission cache first
        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        // Seed additional roles & permissions
        $aclSyncService = $this->app->make(\App\Services\RolePermissionSyncService::class);
        $seeder = new \Database\Seeders\RolePermissionSeeder($aclSyncService);
        $seeder->run();

        // Reset cached roles and permissions
        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Create global test users
        $this->setUpUsers();
    }

    protected function setUpUsers(): void
    {
        // Ensure permissions are re-cached for these new users
        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
