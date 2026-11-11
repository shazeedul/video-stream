<?php

declare(strict_types=1);

use App\Models\SocialAccount;
use App\Models\User;
use Carbon\Carbon;

it('belongs to a user', function () {
    $user = User::factory()->create();
    $socialAccount = SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => '12345',
        'token' => 'token',
    ]);

    expect($socialAccount->user)->toBeInstanceOf(User::class);
    expect($socialAccount->user->id)->toBe($user->id);
});

it('can find by provider and id', function () {
    $user = User::factory()->create();
    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'github',
        'provider_id' => 'github-123',
    ]);

    $found = SocialAccount::where('provider', 'github')
        ->where('provider_id', 'github-123')
        ->first();

    expect($found)->not->toBeNull();
    expect($found->user_id)->toBe($user->id);
});

/*
 * |--------------------------------------------------------------------------
 * | Token Expiration Tests
 * |--------------------------------------------------------------------------
 */

it('checks if token is expired', function () {
    $user = User::factory()->create();

    $expiredAccount = SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'expired-123',
        'expires_at' => Carbon::now()->subHour(),
    ]);

    $validAccount = SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'github',
        'provider_id' => 'valid-123',
        'expires_at' => Carbon::now()->addHour(),
    ]);

    $noExpiryAccount = SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'facebook',
        'provider_id' => 'no-expiry-123',
        'expires_at' => null,
    ]);

    expect($expiredAccount->isTokenExpired())->toBeTrue();
    expect($validAccount->isTokenExpired())->toBeFalse();
    expect($noExpiryAccount->isTokenExpired())->toBeFalse();
});

/*
 * |--------------------------------------------------------------------------
 * | Metadata Accessor Tests
 * |--------------------------------------------------------------------------
 */

it('returns avatar from metadata', function () {
    $user = User::factory()->create();

    $accountWithAvatar = SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'avatar-123',
        'metadata' => ['avatar' => 'https://example.com/avatar.jpg'],
    ]);

    $accountWithoutAvatar = SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'github',
        'provider_id' => 'no-avatar-123',
        'metadata' => [],
    ]);

    expect($accountWithAvatar->avatar)->toBe('https://example.com/avatar.jpg');
    expect($accountWithoutAvatar->avatar)->toBeNull();
});

it('returns email from metadata', function () {
    $user = User::factory()->create();

    $account = SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'email-123',
        'metadata' => ['email' => 'social@example.com'],
    ]);

    expect($account->email)->toBe('social@example.com');
});

it('returns name from metadata', function () {
    $user = User::factory()->create();

    $account = SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'name-123',
        'metadata' => ['name' => 'John Doe'],
    ]);

    expect($account->name)->toBe('John Doe');
});

it('returns username from metadata', function () {
    $user = User::factory()->create();

    $accountWithUsername = SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'github',
        'provider_id' => 'username-123',
        'metadata' => ['username' => 'johndoe'],
    ]);

    $accountWithNickname = SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'twitter',
        'provider_id' => 'nickname-123',
        'metadata' => ['nickname' => 'johnny'],
    ]);

    expect($accountWithUsername->username)->toBe('johndoe');
    expect($accountWithNickname->username)->toBe('johnny');
});
