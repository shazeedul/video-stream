<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

/*
 * |--------------------------------------------------------------------------
 * | Password Edit Tests
 * |--------------------------------------------------------------------------
 */

it('displays password edit page for admin', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get(route('admin.settings.password.edit'))
        ->assertOk();
});

/*
 * |--------------------------------------------------------------------------
 * | Password Update Tests
 * |--------------------------------------------------------------------------
 */

it('updates user password', function () {
    $admin = adminUser();
    $oldPasswordHash = $admin->password;

    actingAs($admin)
        ->put(route('admin.settings.password.update'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertRedirect();

    expect(Hash::check('new-password-123', $admin->fresh()->password))->toBeTrue();
});

it('requires current password to update', function () {
    $admin = adminUser();

    actingAs($admin)
        ->put(route('admin.settings.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertSessionHasErrors(['current_password']);
});

it('validates password confirmation', function () {
    $admin = adminUser();

    actingAs($admin)
        ->put(route('admin.settings.password.update'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'different-password',
        ])
        ->assertSessionHasErrors(['password']);
});

it('validates password minimum length', function () {
    $admin = adminUser();

    actingAs($admin)
        ->put(route('admin.settings.password.update'), [
            'current_password' => 'password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
        ->assertSessionHasErrors(['password']);
});
