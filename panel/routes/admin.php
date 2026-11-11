<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GlobalSearchController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\Settings\PasswordController;
use App\Http\Controllers\Admin\Settings\ProfileController;
use App\Http\Controllers\Admin\Settings\UserPreferencesController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('panel/admin')
    ->as('admin.')
    ->middleware(['auth', 'verified', 'admin.panel'])
    ->group(function (): void {
        Route::get('dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');
        Route::get('dashboard/stats', [DashboardController::class, 'stats'])
            ->name('dashboard.stats');

        // Global Search routes
        Route::prefix('search')->name('search.')->group(function (): void {
            Route::get('/', [GlobalSearchController::class, 'search'])->name('index');
            Route::get('/categories', [GlobalSearchController::class, 'categories'])->name('categories');
            Route::get('/category/{category}', [GlobalSearchController::class, 'searchInCategory'])->name('category');
        });

        // Notification routes
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
         * roles routes
         */
        Route::prefix('roles')->name('roles.')->middleware('permission:roles.view')->group(function (): void {
            Route::get('/', [RoleController::class, 'index'])->name('index');
            Route::get('create', [RoleController::class, 'create'])->middleware('permission:roles.create')->name('create');
            Route::post('/', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('store');
            Route::get('{role}', [RoleController::class, 'show'])->name('show');
            Route::get('{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.update')->name('edit');
            Route::put('{role}', [RoleController::class, 'update'])->middleware('permission:roles.update')->name('update');
            Route::patch('{role}', [RoleController::class, 'update'])->middleware('permission:roles.update');
            Route::delete('{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('destroy');
        });

        /*
         * tenants routes
         */
        Route::prefix('tenants')->name('tenants.')->middleware('permission:tenants.view')->group(function (): void {
            Route::get('/', [TenantController::class, 'index'])->name('index');
            Route::post('/', [TenantController::class, 'store'])->middleware('permission:tenants.create')->name('store');
            Route::get('/{tenant}', [TenantController::class, 'show'])->name('show');
            Route::put('/{tenant}', [TenantController::class, 'update'])->middleware('permission:tenants.update')->name('update');
            Route::patch('/{tenant}/status', [TenantController::class, 'updateStatus'])->middleware('permission:tenants.suspend')->name('status');
            Route::delete('/{tenant}', [TenantController::class, 'destroy'])->middleware('permission:tenants.delete')->name('destroy');
        });

        /*
         * users routes
         */
        Route::prefix('users')->name('users.')->middleware('permission:users.view')->group(function (): void {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::post('/', [UserController::class, 'store'])->middleware('permission:users.create')->name('store');
            Route::get('{user}', [UserController::class, 'show'])->middleware('request:ajax')->name('show');
            Route::match(['put', 'patch'], '{user}', [UserController::class, 'update'])->middleware('permission:users.update')->name('update');
            Route::delete('{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('destroy');
            Route::post('{user}/verify-email', [UserController::class, 'verifyEmail'])->middleware('permission:users.update')->name('verify-email');
            Route::post('{user}/suspend', [UserController::class, 'suspend'])->middleware('permission:users.update')->name('suspend');
            Route::post('{user}/unsuspend', [UserController::class, 'unsuspend'])->middleware('permission:users.update')->name('unsuspend');
        });

        /*
         * system-settings routes
         */
        Route::prefix('system-settings')->name('system-settings.')->middleware('permission:system-settings.view')->group(function (): void {
            Route::get('/', [SystemSettingController::class, 'index'])->name('index');
            Route::post('/', [SystemSettingController::class, 'store'])->middleware('permission:system-settings.create')->name('store');
            Route::put('{systemSetting}', [SystemSettingController::class, 'update'])->middleware('permission:system-settings.update')->name('update');
            Route::patch('bulk-update', [SystemSettingController::class, 'bulkUpdate'])->middleware('permission:system-settings.update')->name('bulk-update');
            Route::delete('{systemSetting}', [SystemSettingController::class, 'destroy'])->middleware('permission:system-settings.delete')->name('destroy');
            Route::post('{systemSetting}/move-up', [SystemSettingController::class, 'moveUp'])->middleware('permission:system-settings.update')->name('move-up');
            Route::post('{systemSetting}/move-down', [SystemSettingController::class, 'moveDown'])->middleware('permission:system-settings.update')->name('move-down');
        });

        /*
         * Settings routes
         */
        Route::prefix('settings')->name('settings.')->group(function (): void {
            // System routes end
            Route::redirect('/', '/panel/admin/settings/profile');

            // Profile routes
            Route::get('profile', [ProfileController::class, 'overview'])->middleware('permission:profile.view')->name('profile.overview');
            Route::get('profile/edit', [ProfileController::class, 'edit'])->middleware('permission:profile.edit')->name('profile.edit');
            Route::patch('profile', [ProfileController::class, 'update'])->middleware('permission:profile.edit')->name('profile.update');

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
