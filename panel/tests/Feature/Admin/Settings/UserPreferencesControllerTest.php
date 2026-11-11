<?php

declare(strict_types=1);

use function Pest\Laravel\actingAs;

/*
 * |--------------------------------------------------------------------------
 * | User Preferences Index Tests
 * |--------------------------------------------------------------------------
 */

it('displays user preferences page for admin', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get(route('admin.settings.preferences.index'))
        ->assertOk();
});

/*
 * |--------------------------------------------------------------------------
 * | User Preferences Set Tests
 * |--------------------------------------------------------------------------
 */

it('sets a user preference', function () {
    $admin = adminUser();

    actingAs($admin)
        ->put(route('admin.settings.preferences.set', 'theme'), [
            'value' => 'dark',
        ])
        ->assertRedirect();

    expect($admin->fresh()->preferences['theme'] ?? null)->toBe('dark');
});

it('updates an existing preference', function () {
    $admin = adminUser();
    $admin->preferences = ['theme' => 'light'];
    $admin->save();

    actingAs($admin)
        ->patch(route('admin.settings.preferences.set', 'theme'), [
            'value' => 'dark',
        ])
        ->assertRedirect();

    expect($admin->fresh()->preferences['theme'])->toBe('dark');
});

it('sets multiple preferences separately', function () {
    $admin = adminUser();

    actingAs($admin)
        ->put(route('admin.settings.preferences.set', 'locale'), [
            'value' => 'en',
        ])
        ->assertRedirect();

    actingAs($admin)
        ->put(route('admin.settings.preferences.set', 'notifications'), [
            'value' => 'enabled',
        ])
        ->assertRedirect();

    expect($admin->fresh()->preferences['locale'])->toBe('en');
    expect($admin->fresh()->preferences['notifications'])->toBe('enabled');
});
