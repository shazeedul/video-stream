<?php

declare(strict_types=1);

use App\Enums\UserStatus;

it('returns labels for each status', function () {
    expect(UserStatus::ACTIVE->label())->toBe('Active');
    expect(UserStatus::SUSPENDED->label())->toBe('Suspended');
    expect(UserStatus::INACTIVE->label())->toBe('Inactive');
});

it('returns colors for each status', function () {
    expect(UserStatus::ACTIVE->color())->toBe('green');
    expect(UserStatus::SUSPENDED->color())->toBe('red');
    expect(UserStatus::INACTIVE->color())->toBe('gray');
});

it('checks active and suspended helpers', function () {
    expect(UserStatus::ACTIVE->isActive())->toBeTrue();
    expect(UserStatus::ACTIVE->isSuspended())->toBeFalse();

    expect(UserStatus::SUSPENDED->isSuspended())->toBeTrue();
    expect(UserStatus::SUSPENDED->isActive())->toBeFalse();
});
