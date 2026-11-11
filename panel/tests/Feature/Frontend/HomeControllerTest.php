<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * |--------------------------------------------------------------------------
 * | Home Page Tests
 * |--------------------------------------------------------------------------
 */

it('displays home page', function () {
    get(route('home'))
        ->assertOk();
});

it('displays home page for authenticated user', function () {
    $user = portalUser();

    actingAs($user)
        ->get(route('home'))
        ->assertOk();
});

/*
 * |--------------------------------------------------------------------------
 * | Suspended Page Tests
 * |--------------------------------------------------------------------------
 */

it('displays suspended page for authenticated user', function () {
    $user = portalUser();

    actingAs($user)
        ->get(route('suspended'))
        ->assertOk();
});

it('redirects unauthenticated users from suspended page to login', function () {
    get(route('suspended'))
        ->assertRedirect(route('login'));
});

it('displays suspended page for suspended user', function () {
    $user = User::factory()->suspended()->create();
    $user->assignRole('user');

    actingAs($user)
        ->get(route('suspended'))
        ->assertOk();
});
