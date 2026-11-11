<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class UpdateUserAction
{
    public function execute(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $updateData = [
                'name' => $data['name'],
                'email' => $data['email'],
            ];

            if (! empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            $user->update($updateData);

            // Sync roles
            if (isset($data['roles']) && is_array($data['roles'])) {
                $roles = Role::query()->whereIn('id', $data['roles'])->get();
                $user->syncRoles($roles);
            } else {
                $user->syncRoles([]);
            }

            return $user->load('roles');
        });
    }
}
