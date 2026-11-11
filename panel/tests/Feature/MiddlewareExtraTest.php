<?php

declare(strict_types=1);

use App\Http\Middleware\Api\ApiV1ResponseMiddleware;
use App\Http\Middleware\LocalizerMiddleware;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Route;

it('adds api version headers for json responses', function () {
    Route::middleware(ApiV1ResponseMiddleware::class)->get('/api/test', function () {
        return response()->json(['ok' => true]);
    });

    $response = $this->get('/api/test');

    $response->assertHeader('API-Version', 'v1');
    $response->assertHeader('Content-Type', 'application/json');
});

it('does not add api headers for non-json responses', function () {
    Route::middleware(ApiV1ResponseMiddleware::class)->get('/api/test-plain', function () {
        return response('ok');
    });

    $response = $this->get('/api/test-plain');

    $response->assertHeaderMissing('API-Version');
});

it('sets locale from authenticated user preference', function () {
    config([
        'localizer.available' => ['en' => ['dir' => 'ltr'], 'bn' => ['dir' => 'ltr']],
        'localizer.default' => 'en',
    ]);

    Route::middleware(LocalizerMiddleware::class)->get('/locale-user', fn () => response('ok'));

    $user = User::factory()->create(['preferences' => ['locale' => 'bn']]);
    $this->actingAs($user)->get('/locale-user');

    expect(App::getLocale())->toBe('bn');
});

it('prefers query locale over headers and cookies', function () {
    config([
        'localizer.available' => ['en' => ['dir' => 'ltr'], 'fr' => ['dir' => 'ltr']],
        'localizer.default' => 'en',
    ]);

    Route::middleware(LocalizerMiddleware::class)->get('/locale-query', fn () => response('ok'));

    $this->withCookie('locale', 'en')
        ->withHeader('X-Locale', 'en')
        ->get('/locale-query?locale=fr');

    expect(App::getLocale())->toBe('fr');
});

it('falls back to default locale when invalid', function () {
    config([
        'localizer.available' => ['en' => ['dir' => 'ltr']],
        'localizer.default' => 'en',
    ]);

    Route::middleware(LocalizerMiddleware::class)->get('/locale-default', fn () => response('ok'));

    $this->withHeader('X-Locale', 'de')->get('/locale-default');

    expect(App::getLocale())->toBe('en');
});

it('queues locale cookie after handling request', function () {
    config([
        'localizer.available' => ['en' => ['dir' => 'ltr']],
        'localizer.default' => 'en',
    ]);

    Route::middleware(LocalizerMiddleware::class)->get('/locale-cookie', fn () => response('ok'));

    $this->get('/locale-cookie');

    $queued = Cookie::getQueuedCookies();
    expect($queued)->not->toBeEmpty();
});
