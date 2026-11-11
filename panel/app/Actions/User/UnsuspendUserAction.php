<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;

final class UnsuspendUserAction
{
    public function execute(User $user): User
    {
        $user->unsuspend();

        return $user->refresh();
    }
}
