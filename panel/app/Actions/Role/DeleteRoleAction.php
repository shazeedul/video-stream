<?php

declare(strict_types=1);

namespace App\Actions\Role;

use App\Models\Role;
use Exception;
use Illuminate\Support\Facades\DB;

final class DeleteRoleAction
{
    public function execute(Role $role): bool
    {
        // Prevent deletion of default roles
        throw_if($role->is_default, Exception::class, 'Cannot delete default role');

        // Prevent deletion of roles that have users assigned
        $userCount = $role->users()->count();
        throw_if($userCount > 0, Exception::class, sprintf("Cannot delete role '%s' because it is assigned to %d user(s). Please remove the role from all users before deleting.", $role->display_name, $userCount));

        return DB::transaction(function () use ($role) {
            // Remove all permissions from the role
            $role->syncPermissions([]);

            // Delete the role
            return $role->delete();
        });
    }
}
