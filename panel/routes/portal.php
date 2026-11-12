<?php

declare(strict_types=1);

use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\NotificationController;
use App\Http\Controllers\Portal\Settings\OrganizationController;
use App\Http\Controllers\Portal\Settings\OrganizationMemberController;
use App\Http\Controllers\Portal\Settings\PasswordController;
use App\Http\Controllers\Portal\Settings\ProfileController;
use App\Http\Controllers\Portal\Settings\UserPreferencesController;
use App\Http\Controllers\Portal\TenantSwitchController;
use App\Http\Controllers\Portal\Videos\VideoController;
use App\Http\Controllers\Portal\Videos\VideoUploadController;
use Illuminate\Support\Facades\Route;

Route::prefix('panel')
    ->as('portal.')
    ->middleware(['auth', 'verified', 'default.panel', 'tenant.context'])
    ->group(function (): void {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        /*
         * Tenant switcher routes
         */
        Route::post('tenants/switch/{tenant}', [TenantSwitchController::class, 'switch'])->name('tenants.switch');

        /*
         * Video library routes
         */
        Route::resource('videos', VideoController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        /*
         * Video upload routes
         */
        Route::prefix('videos/{video}/upload')->as('videos.upload.')->group(function (): void {
            Route::post('initiate', [VideoUploadController::class, 'initiate'])->name('initiate');
            Route::post('parts', [VideoUploadController::class, 'partUrls'])->name('parts');
            Route::post('complete', [VideoUploadController::class, 'complete'])->name('complete');
            Route::post('abort', [VideoUploadController::class, 'abort'])->name('abort');
        });

        /*
         * Notification routes
         */
        Route::prefix('notifications')->name('notifications.')->group(function (): void {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::get('/recent', [NotificationController::class, 'recent'])->middleware('request:ajax')->name('recent');
            Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->middleware('request:ajax')->name('unread-count');
            Route::get('/test', [NotificationController::class, 'test'])->name('test');
            Route::get('/{notification}', [NotificationController::class, 'show'])->name('show');
            Route::patch('/{notification}/read', [NotificationController::class, 'markAsRead'])->name('mark-as-read');
            Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
            Route::delete('/{notification}', [NotificationController::class, 'destroy'])->name('destroy');
            Route::delete('/', [NotificationController::class, 'destroyAll'])->name('destroy-all');
        });

        /*
         * Settings routes
         */
        Route::prefix('settings')->name('settings.')->group(function (): void {
            // System routes end
            Route::redirect('/', '/panel/settings/profile');

            // Profile routes
            Route::get('profile', [ProfileController::class, 'overview'])->middleware('permission:profile.view')->name('profile.overview');
            Route::get('profile/edit', [ProfileController::class, 'edit'])->middleware('permission:profile.edit')->name('profile.edit');
            Route::patch('profile', [ProfileController::class, 'update'])->middleware('permission:profile.edit')->name('profile.update');

            // Organization routes
            Route::get('organization', [OrganizationController::class, 'overview'])->name('organization.overview');
            Route::put('organization', [OrganizationController::class, 'update'])->name('organization.update');

            // Organization member routes
            Route::prefix('organization/members')->name('organization.members.')->group(function (): void {
                Route::get('/', [OrganizationMemberController::class, 'index'])->name('index');
                Route::post('/', [OrganizationMemberController::class, 'store'])->name('store');
                Route::patch('{membership}', [OrganizationMemberController::class, 'update'])->name('update');
                Route::delete('{membership}', [OrganizationMemberController::class, 'destroy'])->name('destroy');
            });

            // Account deletion routes
            Route::get('profile/delete', [ProfileController::class, 'delete'])->middleware('permission:profile.delete')->name('profile.delete');
            Route::post('profile/delete/schedule', [ProfileController::class, 'scheduleForDeletion'])->middleware('permission:profile.delete')->name('profile.delete.schedule');
            Route::post('profile/delete/cancel', [ProfileController::class, 'cancelDeletion'])->middleware('permission:profile.delete')->name('profile.delete.cancel');
            Route::delete('profile', [ProfileController::class, 'destroy'])->middleware('permission:profile.delete')->name('profile.destroy');

            // Password routes
            Route::get('password', [PasswordController::class, 'edit'])->middleware('permission:profile.password.change')->name('password.edit');

            Route::put('password', [PasswordController::class, 'update'])
                ->middleware(['throttle:6,1', 'permission:profile.password.change'])
                ->name('password.update');

            // User Preferences routes
            Route::get('preferences', [UserPreferencesController::class, 'index'])->middleware('permission:profile.preferences')->name('preferences.index');
            Route::match(['put', 'patch'], 'preferences/{key}', [UserPreferencesController::class, 'set'])->middleware('permission:profile.preferences')->name('preferences.set');
        });
    });
