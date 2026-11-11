<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Notification;

it('shows password reset request page', function () {
    test()->get(route('password.request'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('auth/forgot-password'));
});

it('sends password reset link for valid email', function () {
    Notification::fake();

    $user = User::factory()->create();

    test()->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect()
        ->assertSessionHas('status');
    // Ensure the notification was sent
    Notification::assertSentTo([$user], Illuminate\Auth\Notifications\ResetPassword::class);
});

it('does not reveal whether email exists', function () {
    test()->post(route('password.email'), ['email' => 'nonexistent@example.com'])
        ->assertRedirect()
        ->assertSessionHas('status', __('A reset link will be sent if the account exists.'));
});

it('validates email format for password reset', function () {
    test()->post(route('password.email'), ['email' => 'invalid-email'])
        ->assertSessionHasErrors(['email']);
});

it('requires email for password reset', function () {
    test()->post(route('password.email'), [])
        ->assertSessionHasErrors(['email']);
});

it('shows status message from session', function () {
    test()->withSession(['status' => 'password-reset-sent'])
        ->get(route('password.request'))
        ->assertSuccessful();
});
