<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

it('shows password reset page with token', function () {
    test()->get(route('password.reset', ['token' => 'test-token', 'email' => 'test@example.com']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('auth/reset-password')
            ->has('email')
            ->has('token'));
});

it('resets password with valid token', function () {
    Event::fake([PasswordReset::class]);

    $user = User::factory()->create();
    $token = Password::createToken($user);

    test()->post(route('password.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertRedirect(route('login'))
        ->assertSessionHas('status');

    expect(Hash::check('NewPassword123!', $user->fresh()->password))->toBeTrue();
    Event::assertDispatched(PasswordReset::class);
});

it('validates required fields for password reset', function () {
    test()->post(route('password.store'), [])
        ->assertSessionHasErrors(['token', 'email', 'password']);
});

it('validates email format for password reset', function () {
    test()->post(route('password.store'), [
        'token' => 'test-token',
        'email' => 'invalid-email',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertSessionHasErrors(['email']);
});

it('validates password confirmation for reset', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    test()->post(route('password.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'DifferentPassword123!',
    ])->assertSessionHasErrors(['password']);
});

it('validates password strength for reset', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    test()->post(route('password.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'weak',
        'password_confirmation' => 'weak',
    ])->assertSessionHasErrors(['password']);
});

it('rejects invalid reset token', function () {
    $user = User::factory()->create();

    test()->post(route('password.store'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertSessionHasErrors(['email']);
});

it('rejects expired reset token', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    // Fast forward time
    $this->travel(61)->minutes();

    test()->post(route('password.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertSessionHasErrors(['email']);
});

it('regenerates remember token after password reset', function () {
    $user = User::factory()->create();
    $oldRememberToken = $user->remember_token;
    $token = Password::createToken($user);

    test()->post(route('password.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    expect($user->fresh()->remember_token)->not->toBe($oldRememberToken);
});
