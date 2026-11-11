<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createUsers();
        $this->createAdminUser();
    }

    public function createUsers(): void
    {
        $user = User::factory()->create([
            'name' => 'General User',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'phone' => '+1 (555) 000-0002',
            'preferences' => [
                'theme' => ['light', 'dark', 'auto'][array_rand(['light', 'dark', 'auto'])],
                'locale' => 'en',
                'notifications' => [
                    'email' => true,
                ],
            ],
        ]);
        $user->assignRole('user');
    }

    public function createAdminUser(): void
    {
        $roles = Role::query()->get();

        foreach ($roles as $role) {
            // Skip the 'user' role as it's already created above
            if ($role->name === 'user') {
                continue;
            }

            // Create a user for each admin role with preferences
            $user = User::factory()->create([
                'name' => ucfirst((string) $role->name).' User',
                'email' => mb_strtolower((string) $role->name).'@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'phone' => '+1 (555) 000-0002',
                'preferences' => [
                    'theme' => ['light', 'dark', 'auto'][array_rand(['light', 'dark', 'auto'])],
                    'locale' => 'en',
                    'notifications' => [
                        'email' => true,
                    ],
                ],
            ]);

            $user->assignRole($role);
        }
    }
}
