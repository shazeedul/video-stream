<?php

declare(strict_types=1);

use App\Notifications\GeneralNotification;

use function Pest\Laravel\actingAs;

/*
 * |--------------------------------------------------------------------------
 * | Notification Index Tests
 * |--------------------------------------------------------------------------
 */

it('displays notifications index page for portal user', function () {
    $user = portalUser();

    actingAs($user)
        ->get(route('portal.notifications.index'))
        ->assertOk();
});

it('filters notifications by status', function () {
    $user = portalUser();

    actingAs($user)
        ->get(route('portal.notifications.index', ['status' => 'unread']))
        ->assertOk();
});

it('filters notifications by read status', function () {
    $user = portalUser();

    // Create a notification and mark it as read
    $user->notify(new GeneralNotification(
        title: 'Read Notification',
        message: 'This is already read',
        actionUrl: route('portal.dashboard'),
        notificationType: 'info'
    ));
    $user->notifications()->first()->markAsRead();

    actingAs($user)
        ->get(route('portal.notifications.index', ['status' => 'read']))
        ->assertOk();
});

it('filters notifications by level', function () {
    $user = portalUser();

    $user->notify(new GeneralNotification(
        title: 'Error Notification',
        message: 'This is an error',
        actionUrl: route('portal.dashboard'),
        notificationType: 'error'
    ));

    actingAs($user)
        ->get(route('portal.notifications.index', ['level' => 'error']))
        ->assertOk();
});

it('shows all levels when level is all', function () {
    $user = portalUser();

    actingAs($user)
        ->get(route('portal.notifications.index', ['level' => 'all']))
        ->assertOk();
});

/*
 * |--------------------------------------------------------------------------
 * | Notification Show Tests
 * |--------------------------------------------------------------------------
 */

it('marks notification as read and redirects', function () {
    $user = portalUser();
    $user->notify(new GeneralNotification(
        title: 'Test Notification',
        message: 'Test message',
        actionUrl: route('portal.dashboard'),
        notificationType: 'info'
    ));

    $notification = $user->notifications()->first();

    actingAs($user)
        ->get(route('portal.notifications.show', $notification->id))
        ->assertRedirect(route('portal.dashboard'));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('redirects to notifications index when action URL is missing', function () {
    $user = portalUser();

    // Create notification without action URL
    $user->notify(new GeneralNotification(
        title: 'No Action URL',
        message: 'This has no action URL',
        actionUrl: null,
        notificationType: 'info'
    ));

    $notification = $user->notifications()->first();

    actingAs($user)
        ->get(route('portal.notifications.show', $notification->id))
        ->assertRedirect(route('portal.notifications.index'));
});

it('does not re-mark already read notification', function () {
    $user = portalUser();
    $user->notify(new GeneralNotification(
        title: 'Already Read',
        message: 'Already marked as read',
        actionUrl: route('portal.dashboard'),
        notificationType: 'info'
    ));

    $notification = $user->notifications()->first();
    $notification->markAsRead();
    $originalReadAt = $notification->read_at;

    actingAs($user)
        ->get(route('portal.notifications.show', $notification->id))
        ->assertRedirect();

    expect($notification->fresh()->read_at->toDateTimeString())->toBe($originalReadAt->toDateTimeString());
});

/*
 * |--------------------------------------------------------------------------
 * | Mark As Read Tests
 * |--------------------------------------------------------------------------
 */

it('marks a notification as read', function () {
    $user = portalUser();
    $user->notify(new GeneralNotification(
        title: 'Test',
        message: 'Test message',
        actionUrl: route('portal.dashboard'),
        notificationType: 'info'
    ));

    $notification = $user->notifications()->first();

    actingAs($user)
        ->patch(route('portal.notifications.mark-as-read', $notification->id))
        ->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks all notifications as read', function () {
    $user = portalUser();

    for ($i = 0; $i < 3; $i++) {
        $user->notify(new GeneralNotification(
            title: "Notification {$i}",
            message: 'Test message',
            actionUrl: route('portal.dashboard'),
            notificationType: 'info'
        ));
    }

    actingAs($user)
        ->post(route('portal.notifications.mark-all-read'))
        ->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
});

/*
 * |--------------------------------------------------------------------------
 * | Notification Delete Tests
 * |--------------------------------------------------------------------------
 */

it('deletes a notification', function () {
    $user = portalUser();
    $user->notify(new GeneralNotification(
        title: 'Test',
        message: 'Test message',
        actionUrl: route('portal.dashboard'),
        notificationType: 'info'
    ));

    $notification = $user->notifications()->first();
    $notificationId = $notification->id;

    actingAs($user)
        ->delete(route('portal.notifications.destroy', $notificationId))
        ->assertRedirect();

    expect($user->notifications()->where('id', $notificationId)->exists())->toBeFalse();
});

it('deletes all notifications', function () {
    $user = portalUser();

    for ($i = 0; $i < 3; $i++) {
        $user->notify(new GeneralNotification(
            title: "Notification {$i}",
            message: 'Test message',
            actionUrl: route('portal.dashboard'),
            notificationType: 'info'
        ));
    }

    actingAs($user)
        ->delete(route('portal.notifications.destroy-all'))
        ->assertRedirect();

    expect($user->notifications()->count())->toBe(0);
});

/*
 * |--------------------------------------------------------------------------
 * | Notification API Tests
 * |--------------------------------------------------------------------------
 */

it('returns unread notifications count', function () {
    $user = portalUser();

    for ($i = 0; $i < 5; $i++) {
        $user->notify(new GeneralNotification(
            title: "Notification {$i}",
            message: 'Test message',
            actionUrl: route('portal.dashboard'),
            notificationType: 'info'
        ));
    }

    actingAs($user)
        ->get(route('portal.notifications.unread-count'), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertJson(['count' => 5]);
});

it('returns recent notifications', function () {
    $user = portalUser();

    $user->notify(new GeneralNotification(
        title: 'Test',
        message: 'Test message',
        actionUrl: route('portal.dashboard'),
        notificationType: 'info'
    ));

    actingAs($user)
        ->get(route('portal.notifications.recent'), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertJsonStructure([
            'notifications',
            'unread_count',
        ]);
});

/*
 * |--------------------------------------------------------------------------
 * | Test Notification Endpoint
 * |--------------------------------------------------------------------------
 */

it('sends a test notification', function () {
    $user = portalUser();

    actingAs($user)
        ->get(route('portal.notifications.test'))
        ->assertOk();

    expect($user->notifications()->count())->toBeGreaterThan(0);
});
