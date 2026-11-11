<?php

declare(strict_types=1);

use App\Notifications\GeneralNotification;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

/*
 * |--------------------------------------------------------------------------
 * | Notification Index Tests
 * |--------------------------------------------------------------------------
 */

it('displays notifications index page for admin', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get(route('admin.notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/notifications/index')
            ->has('notifications')
            ->has('filters'));
});

it('filters notifications by status', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get(route('admin.notifications.index', ['status' => 'unread']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.status', 'unread'));
});

it('filters notifications by level', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get(route('admin.notifications.index', ['level' => 'info']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.level', 'info'));
});

/*
 * |--------------------------------------------------------------------------
 * | Notification Show Tests
 * |--------------------------------------------------------------------------
 */

it('marks notification as read and redirects', function () {
    $admin = adminUser();
    $admin->notify(new GeneralNotification(
        title: 'Test Notification',
        message: 'Test message',
        actionUrl: route('admin.dashboard'),
        notificationType: 'info'
    ));

    $notification = $admin->notifications()->first();

    actingAs($admin)
        ->get(route('admin.notifications.show', $notification->id))
        ->assertRedirect(route('admin.dashboard'));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('redirects to notifications index when no action url', function () {
    $admin = adminUser();
    $admin->notify(new GeneralNotification(
        title: 'Test Notification',
        message: 'Test message',
        actionUrl: null,
        notificationType: 'info'
    ));

    $notification = $admin->notifications()->first();

    actingAs($admin)
        ->get(route('admin.notifications.show', $notification->id))
        ->assertRedirect(route('admin.notifications.index'));
});

/*
 * |--------------------------------------------------------------------------
 * | Mark As Read Tests
 * |--------------------------------------------------------------------------
 */

it('marks a notification as read', function () {
    $admin = adminUser();
    $admin->notify(new GeneralNotification(
        title: 'Test Notification',
        message: 'Test message',
        actionUrl: route('admin.dashboard'),
        notificationType: 'info'
    ));

    $notification = $admin->notifications()->first();

    actingAs($admin)
        ->patch(route('admin.notifications.mark-as-read', $notification->id))
        ->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks all notifications as read', function () {
    $admin = adminUser();

    // Create multiple notifications
    for ($i = 0; $i < 3; $i++) {
        $admin->notify(new GeneralNotification(
            title: "Notification {$i}",
            message: 'Test message',
            actionUrl: route('admin.dashboard'),
            notificationType: 'info'
        ));
    }

    expect($admin->unreadNotifications()->count())->toBe(3);

    actingAs($admin)
        ->post(route('admin.notifications.mark-all-read'))
        ->assertRedirect();

    expect($admin->unreadNotifications()->count())->toBe(0);
});

/*
 * |--------------------------------------------------------------------------
 * | Notification Delete Tests
 * |--------------------------------------------------------------------------
 */

it('deletes a notification', function () {
    $admin = adminUser();
    $admin->notify(new GeneralNotification(
        title: 'Test Notification',
        message: 'Test message',
        actionUrl: route('admin.dashboard'),
        notificationType: 'info'
    ));

    $notification = $admin->notifications()->first();
    $notificationId = $notification->id;

    actingAs($admin)
        ->delete(route('admin.notifications.destroy', $notificationId))
        ->assertRedirect();

    expect($admin->notifications()->where('id', $notificationId)->exists())->toBeFalse();
});

it('deletes all notifications', function () {
    $admin = adminUser();

    // Create multiple notifications
    for ($i = 0; $i < 3; $i++) {
        $admin->notify(new GeneralNotification(
            title: "Notification {$i}",
            message: 'Test message',
            actionUrl: route('admin.dashboard'),
            notificationType: 'info'
        ));
    }

    expect($admin->notifications()->count())->toBe(3);

    actingAs($admin)
        ->delete(route('admin.notifications.destroy-all'))
        ->assertRedirect();

    expect($admin->notifications()->count())->toBe(0);
});

/*
 * |--------------------------------------------------------------------------
 * | Notification API Tests
 * |--------------------------------------------------------------------------
 */

it('returns unread notifications count', function () {
    $admin = adminUser();

    // Create notifications
    for ($i = 0; $i < 5; $i++) {
        $admin->notify(new GeneralNotification(
            title: "Notification {$i}",
            message: 'Test message',
            actionUrl: route('admin.dashboard'),
            notificationType: 'info'
        ));
    }

    actingAs($admin)
        ->get(route('admin.notifications.unread-count'), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertJson(['count' => 5]);
});

it('returns recent notifications', function () {
    $admin = adminUser();

    // Create notifications
    for ($i = 0; $i < 3; $i++) {
        $admin->notify(new GeneralNotification(
            title: "Notification {$i}",
            message: 'Test message',
            actionUrl: route('admin.dashboard'),
            notificationType: 'info'
        ));
    }

    actingAs($admin)
        ->get(route('admin.notifications.recent'), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertJsonStructure([
            'notifications',
            'unread_count',
        ]);
});

/*
 * |--------------------------------------------------------------------------
 * | Test Notification Tests
 * |--------------------------------------------------------------------------
 */

it('sends a test notification', function () {
    $admin = adminUser();

    $initialCount = $admin->notifications()->count();

    actingAs($admin)
        ->get(route('admin.notifications.test'))
        ->assertOk();

    expect($admin->notifications()->count())->toBe($initialCount + 1);
});
