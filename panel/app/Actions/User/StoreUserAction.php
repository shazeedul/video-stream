<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class StoreUserAction
{
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            if (isset($data['roles']) && is_array($data['roles'])) {
                $roles = Role::query()->whereIn('id', $data['roles'])->get();
                $user->assignRole($roles);
            }

            return $user->load('roles');
        });
    }
}
