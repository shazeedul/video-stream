<?php

declare(strict_types=1);

use function Pest\Laravel\actingAs;

/*
 * |--------------------------------------------------------------------------
 * | Dashboard Tests
 * |--------------------------------------------------------------------------
 */

it('displays admin dashboard for admin user', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard'));
});

it('denies dashboard access for unauthorized users', function () {
    $user = portalUser();

    actingAs($user)
        ->get(route('admin.dashboard'))
        // Portal users are redirected by admin.panel middleware
        ->assertRedirect();
});

it('returns dashboard stats for admin', function () {
    $admin = adminUser();

    actingAs($admin)
        ->getJson(route('admin.dashboard.stats'))
        ->assertOk();
});

it('redirects unauthenticated users to login', function () {
    test()
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});
