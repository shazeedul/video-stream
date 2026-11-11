<?php

declare(strict_types=1);

use function Pest\Laravel\actingAs;

/*
 * |--------------------------------------------------------------------------
 * | Portal Dashboard Tests
 * |--------------------------------------------------------------------------
 */

it('displays portal dashboard for authenticated user', function () {
    $user = portalUser();

    actingAs($user)
        ->get(route('portal.dashboard'))
        ->assertOk();
});

it('redirects unauthenticated users to login', function () {
    test()
        ->get(route('portal.dashboard'))
        ->assertRedirect(route('login'));
});

// Note: Admin users accessing the portal are redirected to the admin panel
// by the default.panel middleware, so we only test portal users here
