<?php

declare(strict_types=1);

use App\Actions\User\SuspendUserAction;
use App\Enums\UserStatus;
use App\Models\User;

it('suspends a user with reason', function () {
    $user = User::factory()->create([
        'status' => UserStatus::ACTIVE,
    ]);

    $action = app(SuspendUserAction::class);
    $action->execute($user, 'Violation of terms');

    expect($user->fresh()->isSuspended())->toBeTrue();
    expect($user->fresh()->status)->toBe(UserStatus::SUSPENDED);
    expect($user->fresh()->status_reason)->toBe('Violation of terms');
});
