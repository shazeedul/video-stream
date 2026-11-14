<?php

declare(strict_types=1);

use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Portal\EmbedController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
// Suspended account page
Route::get('/suspended', [HomeController::class, 'suspended'])
    ->middleware('auth')
    ->name('suspended');

Route::get('/embed/videos/{video}', [EmbedController::class, 'show'])->name('videos.embed');

Route::middleware([])
    ->group(base_path('routes/auth.php'));
