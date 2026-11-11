<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;

it('shows email verification prompt for unverified users', function () {
    $user = User::factory()->unverified()->create();

    actingAs($user)->get(route('verification.notice'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('auth/verify-email'));
});

it('redirects verified users to home', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    actingAs($user)->get(route('verification.notice'))
        ->assertRedirect();
});

it('requires authentication to access verification prompt', function () {
    test()->get(route('verification.notice'))
        ->assertRedirect(route('login'));
});

it('shows status message when provided in session', function () {
    $user = User::factory()->unverified()->create();

    actingAs($user)
        ->withSession(['status' => 'verification-link-sent'])
        ->get(route('verification.notice'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('auth/verify-email'));
});
