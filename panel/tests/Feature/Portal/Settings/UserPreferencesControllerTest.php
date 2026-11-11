<?php

declare(strict_types=1);

use function Pest\Laravel\actingAs;

/*
 * |--------------------------------------------------------------------------
 * | User Preferences Index Tests
 * |--------------------------------------------------------------------------
 */

it('displays user preferences page for portal user', function () {
    $user = portalUser();

    actingAs($user)
        ->get(route('portal.settings.preferences.index'))
        ->assertOk();
});

/*
 * |--------------------------------------------------------------------------
 * | User Preferences Set Tests
 * |--------------------------------------------------------------------------
 */

it('sets a portal user preference', function () {
    $user = portalUser();

    actingAs($user)
        ->put(route('portal.settings.preferences.set', 'theme'), [
            'value' => 'dark',
        ])
        ->assertRedirect();

    expect($user->fresh()->preferences['theme'] ?? null)->toBe('dark');
});

it('updates an existing portal user preference', function () {
    $user = portalUser();
    $user->preferences = ['theme' => 'light'];
    $user->save();

    actingAs($user)
        ->patch(route('portal.settings.preferences.set', 'theme'), [
            'value' => 'dark',
        ])
        ->assertRedirect();

    expect($user->fresh()->preferences['theme'])->toBe('dark');
});
