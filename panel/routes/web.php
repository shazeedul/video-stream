<?php

declare(strict_types=1);

use App\Http\Controllers\Frontend\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
// Suspended account page
Route::get('/suspended', [HomeController::class, 'suspended'])
    ->middleware('auth')
    ->name('suspended');

Route::middleware([])
    ->group(base_path('routes/auth.php'));
