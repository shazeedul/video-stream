<?php

declare(strict_types=1);

namespace App\Actions\Role;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

final class UpdateRoleAction
{
    public function execute(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $role->update([
                'name' => $data['name'],
                'display_name' => $data['display_name'],
                'description' => $data['description'] ?? null,
                'guard_name' => $data['guard_name'] ?? 'web',
            ]);

            // Always sync permissions, even if empty (to remove all permissions)
            if (isset($data['permissions']) && is_array($data['permissions'])) {
                if (empty($data['permissions'])) {
                    $role->syncPermissions([]);
                } else {
                    $permissions = Permission::query()->whereIn('id', $data['permissions'])->get();
                    $role->syncPermissions($permissions);
                }
            }

            return $role->load('permissions');
        });
    }
}
