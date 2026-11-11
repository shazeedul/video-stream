<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/*
 * |--------------------------------------------------------------------------
 * | User Status Tests
 * |--------------------------------------------------------------------------
 */

it('checks if user is suspended', function () {
    $user = User::factory()->suspended()->make();

    expect($user->isSuspended())->toBeTrue();
    expect($user->isActive())->toBeFalse();
});

it('checks if user is active', function () {
    $user = User::factory()->make(['status' => UserStatus::ACTIVE]);

    expect($user->isActive())->toBeTrue();
    expect($user->isSuspended())->toBeFalse();
});

/*
 * |--------------------------------------------------------------------------
 * | User Suspend/Unsuspend Tests
 * |--------------------------------------------------------------------------
 */

it('suspends a user', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);

    $user->suspend('Violation of terms');

    expect($user->status)->toBe(UserStatus::SUSPENDED);
    expect($user->status_reason)->toBe('Violation of terms');
    expect($user->status_updated_at)->not->toBeNull();
});

it('unsuspends a user', function () {
    $user = User::factory()->suspended()->create();

    $user->unsuspend();

    expect($user->status)->toBe(UserStatus::ACTIVE);
    expect($user->status_reason)->toBeNull();
    expect($user->status_updated_at)->not->toBeNull();
});

/*
 * |--------------------------------------------------------------------------
 * | User Scope Tests
 * |--------------------------------------------------------------------------
 */

it('filters verified users', function () {
    User::factory()->create(['email_verified_at' => now()]);
    User::factory()->unverified()->create();

    $verifiedUsers = User::verified()->get();

    expect($verifiedUsers)->toHaveCount(1);
});

it('filters active users', function () {
    User::factory()->create(['status' => UserStatus::ACTIVE]);
    User::factory()->suspended()->create();

    $activeUsers = User::activeStatus()->get();

    expect($activeUsers)->toHaveCount(1);
});

it('filters suspended users', function () {
    User::factory()->create(['status' => UserStatus::ACTIVE]);
    User::factory()->suspended()->create();

    $suspendedUsers = User::suspended()->get();

    expect($suspendedUsers)->toHaveCount(1);
});

/*
 * |--------------------------------------------------------------------------
 * | Username Generation Tests
 * |--------------------------------------------------------------------------
 */

it('auto-generates username on create', function () {
    $user = User::factory()->create(['name' => 'John Doe']);

    expect($user->username)->not->toBeNull();
    expect($user->username)->toContain('john');
});

it('generates unique username when duplicate exists', function () {
    $user1 = User::factory()->create(['name' => 'John Doe']);
    $user2 = User::factory()->create(['name' => 'John Doe']);

    expect($user1->username)->not->toBe($user2->username);
});

/*
 * |--------------------------------------------------------------------------
 * | User Relationship Tests
 * |--------------------------------------------------------------------------
 */

it('has social accounts relationship', function () {
    $user = User::factory()->create();

    expect($user->socialAccounts())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
});

it('builds profile photo url and has_password accessor', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);

    $user = User::factory()->create([
        'password' => bcrypt('secret'),
        'profile_photo_path' => 'profile-photos/avatar.jpg',
    ]);

    Storage::disk('public')->put('profile-photos/avatar.jpg', 'data');

    expect($user->profile_photo_url)->toContain('profile-photos/avatar.jpg');
    expect($user->has_password)->toBeTrue();

    $user->update(['password' => null]);
    expect($user->has_password)->toBeFalse();
});

it('generates username from email when name is empty', function () {
    $user = User::factory()->create([
        'name' => '',
        'email' => 'alpha@example.com',
    ]);

    expect($user->username)->toContain('alpha');
});
