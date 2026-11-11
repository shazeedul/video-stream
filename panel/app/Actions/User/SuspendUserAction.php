<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;

final class SuspendUserAction
{
    public function execute(User $user, string $reason): User
    {
        $user->suspend($reason);

        return $user->refresh();
    }
}
