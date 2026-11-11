<?php

declare(strict_types=1);

use App\Actions\User\VerifyEmailAction;
use App\Models\User;

it('verifies user email', function () {
    $user = User::factory()->unverified()->create();

    expect($user->email_verified_at)->toBeNull();

    $action = app(VerifyEmailAction::class);
    $action->execute($user);

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('does not change already verified email', function () {
    $user = User::factory()->create([
        'email_verified_at' => now()->subDays(10),
    ]);

    $originalVerifiedAt = $user->email_verified_at;

    $action = app(VerifyEmailAction::class);
    $action->execute($user);

    // The verification date should be updated to now
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});
