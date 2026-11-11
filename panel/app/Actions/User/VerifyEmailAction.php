<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;

final class VerifyEmailAction
{
    public function execute(User $user): User
    {
        if ($user->hasVerifiedEmail()) {
            return $user;
        }

        $user->markEmailAsVerified();

        return $user->refresh();
    }
}
