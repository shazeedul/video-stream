<?php

declare(strict_types=1);

use App\Http\Middleware\AdminPanelAccess;
use App\Http\Middleware\CheckUserStatus;
use App\Http\Middleware\DefaultPanelAccess;
use App\Http\Middleware\RequestMiddleware;
use App\Models\User;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    Route::get('/test-ajax', function () {
        return response('ok');
    })->middleware(RequestMiddleware::class.':ajax');

    Route::get('/test-http', function () {
        return response('ok');
    })->middleware(RequestMiddleware::class.':http');

    Route::get('/test-status', function () {
        return response('ok');
    })->middleware(CheckUserStatus::class);

    Route::get('/test-admin-access', function () {
        return response('ok');
    })->middleware(AdminPanelAccess::class);

    Route::get('/test-default-access', function () {
        return response('ok');
    })->middleware(DefaultPanelAccess::class);
});

/*
 * |--------------------------------------------------------------------------
 * | RequestMiddleware Tests
 * |--------------------------------------------------------------------------
 */

it('allows ajax request for ajax method', function () {
    get('/test-ajax', ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk();
});

it('denies non-ajax request for ajax method', function () {
    get('/test-ajax')
        ->assertForbidden()
        ->assertSee('Only Ajax Request Allow');
});

it('allows non-ajax request for http method', function () {
    get('/test-http')
        ->assertOk();
});

it('denies ajax request for http method', function () {
    get('/test-http', ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertForbidden()
        ->assertSee('Only Non-Ajax Request Allow');
});

/*
 * |--------------------------------------------------------------------------
 * | CheckUserStatus Tests
 * |--------------------------------------------------------------------------
 */

it('allows active users', function () {
    $user = User::factory()->state(['status' => App\Enums\UserStatus::ACTIVE])->create();

    actingAs($user)
        ->get('/test-status')
        ->assertOk();
});

it('redirects suspended users', function () {
    $user = User::factory()->suspended()->create();

    actingAs($user)
        ->get('/test-status')
        ->assertRedirect(route('suspended'));
});

it('allows guests', function () {
    get('/test-status')
        ->assertOk();
});

/*
 * |--------------------------------------------------------------------------
 * | AdminPanelAccess Tests
 * |--------------------------------------------------------------------------
 */

it('allows admins to access admin panel', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get('/test-admin-access')
        ->assertOk();
});

it('redirects non-admins from admin panel', function () {
    $user = portalUser();

    actingAs($user)
        ->get('/test-admin-access')
        ->assertRedirect(route('portal.dashboard'));
});

it('aborts unauthenticated admin panel access', function () {
    get('/test-admin-access')
        ->assertStatus(401);
});

/*
 * |--------------------------------------------------------------------------
 * | DefaultPanelAccess Tests
 * |--------------------------------------------------------------------------
 */

it('allows portal users to access default panel', function () {
    $user = portalUser();

    actingAs($user)
        ->get('/test-default-access')
        ->assertOk();
});

it('redirects admins from default panel to admin dashboard', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get('/test-default-access')
        ->assertRedirect(route('admin.dashboard'));
});
