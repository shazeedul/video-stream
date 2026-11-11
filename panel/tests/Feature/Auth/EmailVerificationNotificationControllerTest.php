<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

it('resends verification email for unverified users', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    actingAs($user)->post(route('verification.send'))
        ->assertRedirect()
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($user, Illuminate\Auth\Notifications\VerifyEmail::class);
});

it('redirects verified users trying to resend verification', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    actingAs($user)->post(route('verification.send'))
        ->assertRedirect();
});

it('requires authentication to resend verification email', function () {
    test()->post(route('verification.send'))
        ->assertRedirect(route('login'));
});
