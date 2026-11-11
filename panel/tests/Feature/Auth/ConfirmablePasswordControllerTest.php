<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

it('shows password confirmation page', function () {
    $user = User::factory()->create();

    actingAs($user)->get(route('password.confirm'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('auth/confirm-password'));
});

it('confirms password with correct password', function () {
    $password = 'Password123!';
    $user = User::factory()->create(['password' => Hash::make($password)]);

    actingAs($user)->post(route('password.confirm'), ['password' => $password])
        ->assertRedirect()
        ->assertSessionHas('auth.password_confirmed_at');
});

it('rejects incorrect password', function () {
    $user = User::factory()->create(['password' => Hash::make('Password123!')]);

    actingAs($user)->post(route('password.confirm'), ['password' => 'WrongPassword'])
        ->assertSessionHasErrors(['password']);
});

it('requires authentication to confirm password', function () {
    test()->get(route('password.confirm'))
        ->assertRedirect(route('login'));

    test()->post(route('password.confirm'), ['password' => 'test'])
        ->assertRedirect(route('login'));
});

it('sets password confirmation timestamp in session', function () {
    $password = 'Password123!';
    $user = User::factory()->create(['password' => Hash::make($password)]);

    actingAs($user)->post(route('password.confirm'), ['password' => $password]);

    expect(session('auth.password_confirmed_at'))->toBeInt();
});
