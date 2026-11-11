<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class DeleteUserAction
{
    public function execute(User $user): bool
    {
        // Prevent deletion of the current user
        throw_if(Auth::id() === $user->id, Exception::class, 'Cannot delete your own account');

        return DB::transaction(function () use ($user) {
            // Remove all roles from the user
            $user->syncRoles([]);

            // Delete the user
            return $user->delete();
        });
    }
}
