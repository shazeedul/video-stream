<?php

declare(strict_types=1);

use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\PlaybackStreamController;
use App\Http\Controllers\Portal\EmbedController;
use App\Http\Controllers\VideoThumbnailController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthCheckController::class, 'liveness'])->name('health');

Route::get('/', [HomeController::class, 'index'])->name('home');
// Suspended account page
Route::get('/suspended', [HomeController::class, 'suspended'])
    ->middleware('auth')
    ->name('suspended');

Route::get('/embed/videos/{video}', [EmbedController::class, 'show'])->name('videos.embed');

// Direct HLS playback fallback when requested directly through panel port
Route::get('/playback/{video}/{asset}', PlaybackStreamController::class)
    ->where('asset', '.*')
    ->name('playback.stream');
Route::options('/playback/{video}/{asset}', fn () => response('', 204, [
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
    'Access-Control-Allow-Headers' => '*',
]))->where('asset', '.*');

// Video thumbnail delivery endpoints
Route::get('/thumbnails/{video}', [VideoThumbnailController::class, 'show'])->name('videos.thumbnail');
Route::get('/storage/tenants/{path}', [VideoThumbnailController::class, 'showStorage'])->where('path', '.*');
Route::get('/tenants/{path}', [VideoThumbnailController::class, 'showStorage'])->where('path', '.*');

Route::middleware([])
    ->group(base_path('routes/auth.php'));
