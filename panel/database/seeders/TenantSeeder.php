<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Tenants\Models\Tenant;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Enums\TenantUserStatus;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedAcmeStreamingStudio();
        $this->seedNexusMediaLabs();
        $this->seedMultiTenantDemoUser();
    }

    /**
     * Seed primary demo organization: Acme Streaming Studio.
     */
    private function seedAcmeStreamingStudio(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'acme-streaming-studio'],
            [
                'name' => 'Acme Streaming Studio',
                'status' => TenantStatus::ACTIVE,
                'settings' => [
                    'timezone' => 'America/New_York',
                    'default_currency' => 'USD',
                    'max_upload_size_mb' => 2048,
                    'storage_limit_gb' => 100,
                ],
            ]
        );

        $members = [
            [
                'name' => 'Studio Owner',
                'email' => 'studio-owner@example.com',
                'role' => TenantRole::OWNER,
            ],
            [
                'name' => 'Studio Admin',
                'email' => 'studio-admin@example.com',
                'role' => TenantRole::ADMIN,
            ],
            [
                'name' => 'Studio Editor',
                'email' => 'studio-editor@example.com',
                'role' => TenantRole::MEMBER,
            ],
            [
                'name' => 'Studio Viewer',
                'email' => 'studio-viewer@example.com',
                'role' => TenantRole::VIEWER,
            ],
        ];

        foreach ($members as $memberData) {
            $user = $this->createOrGetUser($memberData['name'], $memberData['email']);
            $this->attachTenantMembership($tenant, $user, $memberData['role']);
        }
    }

    /**
     * Seed secondary demo organization: Nexus Media Labs with isolated members.
     */
    private function seedNexusMediaLabs(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'nexus-media-labs'],
            [
                'name' => 'Nexus Media Labs',
                'status' => TenantStatus::ACTIVE,
                'settings' => [
                    'timezone' => 'Europe/London',
                    'default_currency' => 'EUR',
                    'max_upload_size_mb' => 4096,
                    'storage_limit_gb' => 250,
                ],
            ]
        );

        $members = [
            [
                'name' => 'Nexus Owner',
                'email' => 'nexus-owner@example.com',
                'role' => TenantRole::OWNER,
            ],
            [
                'name' => 'Nexus Admin',
                'email' => 'nexus-admin@example.com',
                'role' => TenantRole::ADMIN,
            ],
            [
                'name' => 'Nexus Editor',
                'email' => 'nexus-editor@example.com',
                'role' => TenantRole::MEMBER,
            ],
            [
                'name' => 'Nexus Viewer',
                'email' => 'nexus-viewer@example.com',
                'role' => TenantRole::VIEWER,
            ],
        ];

        foreach ($members as $memberData) {
            $user = $this->createOrGetUser($memberData['name'], $memberData['email']);
            $this->attachTenantMembership($tenant, $user, $memberData['role']);
        }
    }

    /**
     * Seed multi-tenant access for the general demo user to enable immediate testing of tenant switching.
     */
    private function seedMultiTenantDemoUser(): void
    {
        $generalUser = User::query()->where('email', 'user@example.com')->first();

        if ($generalUser === null) {
            $generalUser = $this->createOrGetUser('General User', 'user@example.com');
        }

        $acmeTenant = Tenant::query()->where('slug', 'acme-streaming-studio')->first();
        if ($acmeTenant !== null) {
            $this->attachTenantMembership($acmeTenant, $generalUser, TenantRole::MEMBER);
        }

        $nexusTenant = Tenant::query()->where('slug', 'nexus-media-labs')->first();
        if ($nexusTenant !== null) {
            $this->attachTenantMembership($nexusTenant, $generalUser, TenantRole::VIEWER);
        }
    }

    /**
     * Create or retrieve a user with standard credentials.
     */
    private function createOrGetUser(string $name, string $email): User
    {
        /** @var User $user */
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => UserStatus::ACTIVE,
                'preferences' => [
                    'theme' => 'auto',
                    'locale' => 'en',
                    'notifications' => [
                        'email' => true,
                    ],
                ],
            ]
        );

        if (Role::query()->where('name', 'user')->exists() && ! $user->hasRole('user')) {
            $user->assignRole('user');
        }

        return $user;
    }

    /**
     * Attach or update user tenant membership.
     */
    private function attachTenantMembership(Tenant $tenant, User $user, TenantRole $role): void
    {
        $pivot = $tenant->memberships()->where('user_id', $user->id)->first();

        if ($pivot === null) {
            $tenant->users()->attach($user->id, [
                'id' => (string) Str::uuid(),
                'role' => $role->value,
                'status' => TenantUserStatus::ACTIVE->value,
            ]);
        } else {
            $pivot->update([
                'role' => $role->value,
                'status' => TenantUserStatus::ACTIVE->value,
            ]);
        }
    }
}
