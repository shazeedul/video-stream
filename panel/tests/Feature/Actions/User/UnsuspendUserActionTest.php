<?php

declare(strict_types=1);

use App\Actions\User\UnsuspendUserAction;
use App\Enums\UserStatus;
use App\Models\User;

it('unsuspends a suspended user', function () {
    $user = User::factory()->suspended()->create();

    expect($user->isSuspended())->toBeTrue();

    $action = app(UnsuspendUserAction::class);
    $action->execute($user);

    expect($user->fresh()->isActive())->toBeTrue();
    expect($user->fresh()->status)->toBe(UserStatus::ACTIVE);
    expect($user->fresh()->status_reason)->toBeNull();
});
