<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    // Ensure the 'user' role exists for each test
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web'], ['display_name' => 'User']);
});

it('redirects to google provider', function () {
    test()->get(route('social.redirect', 'google'))
        ->assertRedirect();
});

it('redirects to github provider', function () {
    test()->get(route('social.redirect', 'github'))
        ->assertRedirect();
});

it('rejects unsupported social provider', function () {
    test()->get(route('social.redirect', 'unsupported'))
        ->assertNotFound();
});

it('creates new user from social login', function () {
    // Ensure default 'user' role exists
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web'], ['display_name' => 'User']);
    $socialUser = Mockery::mock(SocialiteUser::class);
    $socialUser->shouldReceive('getId')->andReturn('123456');
    $socialUser->shouldReceive('getEmail')->andReturn('social@example.com');
    $socialUser->shouldReceive('getName')->andReturn('Social User');
    $socialUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');
    $socialUser->shouldReceive('getNickname')->andReturn('socialuser');
    $socialUser->shouldReceive('offsetGet')->with('token')->andReturn('access-token');
    $socialUser->shouldReceive('offsetGet')->with('refreshToken')->andReturn(null);
    $socialUser->shouldReceive('offsetGet')->with('expiresIn')->andReturn(null);
    $socialUser->token = 'access-token';
    $socialUser->refreshToken = null;
    $socialUser->expiresIn = null;

    Socialite::shouldReceive('driver->user')->andReturn($socialUser);

    test()->get(route('social.callback', 'google'))
        ->assertRedirect();

    assertDatabaseHas('users', [
        'email' => 'social@example.com',
        'name' => 'Social User',
    ]);

    assertDatabaseHas('social_accounts', [
        'provider' => 'google',
        'provider_id' => '123456',
    ]);

    test()->assertAuthenticated();
});

it('links social account to existing user by email', function () {
    $user = User::factory()->create([
        'email' => 'existing@example.com',
        'password' => Hash::make('password'),
    ]);

    $socialUser = Mockery::mock(SocialiteUser::class);
    $socialUser->shouldReceive('getId')->andReturn('123456');
    $socialUser->shouldReceive('getEmail')->andReturn('existing@example.com');
    $socialUser->shouldReceive('getName')->andReturn('Existing User');
    $socialUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');
    $socialUser->shouldReceive('getNickname')->andReturn('existinguser');
    $socialUser->shouldReceive('offsetGet')->with('token')->andReturn('access-token');
    $socialUser->shouldReceive('offsetGet')->with('refreshToken')->andReturn(null);
    $socialUser->shouldReceive('offsetGet')->with('expiresIn')->andReturn(null);
    $socialUser->token = 'access-token';
    $socialUser->refreshToken = null;
    $socialUser->expiresIn = null;

    Socialite::shouldReceive('driver->user')->andReturn($socialUser);

    test()->get(route('social.callback', 'google'))
        ->assertRedirect();

    assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => '123456',
    ]);

    // Password should be removed when social account is added
    expect($user->fresh()->password)->toBeNull();
});

it('logs in existing user with social account', function () {
    $user = User::factory()->create();
    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => '123456',
        'access_token' => 'old-token',
    ]);

    $socialUser = Mockery::mock(SocialiteUser::class);
    $socialUser->shouldReceive('getId')->andReturn('123456');
    $socialUser->shouldReceive('getEmail')->andReturn($user->email);
    $socialUser->shouldReceive('getName')->andReturn($user->name);
    $socialUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');
    $socialUser->shouldReceive('getNickname')->andReturn('username');
    $socialUser->shouldReceive('offsetGet')->with('token')->andReturn('new-token');
    $socialUser->shouldReceive('offsetGet')->with('refreshToken')->andReturn(null);
    $socialUser->shouldReceive('offsetGet')->with('expiresIn')->andReturn(3600);
    $socialUser->token = 'new-token';
    $socialUser->refreshToken = null;
    $socialUser->expiresIn = 3600;

    Socialite::shouldReceive('driver->user')->andReturn($socialUser);

    test()->get(route('social.callback', 'google'))
        ->assertRedirect();

    test()->assertAuthenticated();

    // Token should be updated
    assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'access_token' => 'new-token',
    ]);
});

it('handles invalid state exception from social provider', function () {
    Socialite::shouldReceive('driver->user')
        ->andThrow(new Laravel\Socialite\Two\InvalidStateException);

    test()->get(route('social.callback', 'google'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['social']);
});

it('handles general exception from social provider', function () {
    Socialite::shouldReceive('driver->user')
        ->andThrow(new Exception('Something went wrong'));

    test()->get(route('social.callback', 'google'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['social']);
});

it('unlinks social account', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);
    $socialAccount = SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => '123456',
        'access_token' => 'token',
    ]);

    actingAs($user)->delete(route('social.unlink', 'google'))
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(SocialAccount::find($socialAccount->id))->toBeNull();
});

it('requires password when unlinking last social account for social-only user', function () {
    $user = User::factory()->create(['password' => null]); // Social-only user
    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => '123456',
        'access_token' => 'token',
    ]);

    actingAs($user)->delete(route('social.unlink', 'google'), [
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertRedirect()
        ->assertSessionHas('status');

    expect(Hash::check('NewPassword123!', $user->fresh()->password))->toBeTrue();
});

it('returns error when unlinking non-existent social account', function () {
    $user = User::factory()->create();

    actingAs($user)->delete(route('social.unlink', 'google'))
        ->assertRedirect()
        ->assertSessionHasErrors(['social']);
});

it('assigns default role to new social users', function () {
    $socialUser = Mockery::mock(SocialiteUser::class);
    $socialUser->shouldReceive('getId')->andReturn('123456');
    $socialUser->shouldReceive('getEmail')->andReturn('newuser@example.com');
    $socialUser->shouldReceive('getName')->andReturn('New User');
    $socialUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');
    $socialUser->shouldReceive('getNickname')->andReturn('newuser');
    $socialUser->shouldReceive('offsetGet')->with('token')->andReturn('access-token');
    $socialUser->shouldReceive('offsetGet')->with('refreshToken')->andReturn(null);
    $socialUser->shouldReceive('offsetGet')->with('expiresIn')->andReturn(null);
    $socialUser->token = 'access-token';
    $socialUser->refreshToken = null;
    $socialUser->expiresIn = null;

    Socialite::shouldReceive('driver->user')->andReturn($socialUser);

    test()->get(route('social.callback', 'google'));

    $user = User::query()->where('email', 'newuser@example.com')->first();
    expect($user->hasRole('user'))->toBeTrue();
});

it('verifies email for new social users', function () {
    $socialUser = Mockery::mock(SocialiteUser::class);
    $socialUser->shouldReceive('getId')->andReturn('123456');
    $socialUser->shouldReceive('getEmail')->andReturn('verified@example.com');
    $socialUser->shouldReceive('getName')->andReturn('Verified User');
    $socialUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');
    $socialUser->shouldReceive('getNickname')->andReturn('verified');
    $socialUser->shouldReceive('offsetGet')->with('token')->andReturn('access-token');
    $socialUser->shouldReceive('offsetGet')->with('refreshToken')->andReturn(null);
    $socialUser->shouldReceive('offsetGet')->with('expiresIn')->andReturn(null);
    $socialUser->token = 'access-token';
    $socialUser->refreshToken = null;
    $socialUser->expiresIn = null;

    Socialite::shouldReceive('driver->user')->andReturn($socialUser);

    test()->get(route('social.callback', 'google'));

    assertDatabaseHas('users', [
        'email' => 'verified@example.com',
    ]);

    $user = User::query()->where('email', 'verified@example.com')->first();
    expect($user->hasVerifiedEmail())->toBeTrue();
});
