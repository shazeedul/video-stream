<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

/*
 * |--------------------------------------------------------------------------
 * | Password Edit Tests
 * |--------------------------------------------------------------------------
 */

it('displays password edit page for portal user', function () {
    $user = portalUser();

    actingAs($user)
        ->get(route('portal.settings.password.edit'))
        ->assertOk();
});

/*
 * |--------------------------------------------------------------------------
 * | Password Update Tests
 * |--------------------------------------------------------------------------
 */

it('updates portal user password', function () {
    $user = portalUser();

    actingAs($user)
        ->put(route('portal.settings.password.update'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertRedirect();

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

it('requires current password to update', function () {
    $user = portalUser();

    actingAs($user)
        ->put(route('portal.settings.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertSessionHasErrors(['current_password']);
});

it('validates password confirmation', function () {
    $user = portalUser();

    actingAs($user)
        ->put(route('portal.settings.password.update'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'different-password',
        ])
        ->assertSessionHasErrors(['password']);
});
