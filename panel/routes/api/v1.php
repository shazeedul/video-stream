<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Internal\ProcessingCallbackController;
use App\Http\Middleware\VerifyInternalSecret;
use Illuminate\Support\Facades\Route;

// API Version 1 Routes
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('internal')->middleware(VerifyInternalSecret::class)->group(function (): void {
        Route::post('/processing/callback', ProcessingCallbackController::class)->name('internal.processing.callback');
    });
});
