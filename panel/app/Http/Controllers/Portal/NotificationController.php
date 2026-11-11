<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Notifications\GeneralNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class NotificationController extends Controller
{
    /**
     * Display a listing of the user's notifications.
     */
    public function index(Request $request): Response
    {
        $status = $request->input('status');
        $level = $request->input('level');

        $query = $request->user()->notifications();

        if ($status === 'unread') {
            $query->whereNull('read_at');
        } elseif ($status === 'read') {
            $query->whereNotNull('read_at');
        }

        if ($level && $level !== 'all') {
            if (DB::connection()->getDriverName() === 'pgsql') {
                $query->whereRaw("(\"data\"::jsonb->>'level') = ?", [$level]);
            } else {
                $query->where('data->level', $level);
            }
        }

        return Inertia::render('portal/notifications/index', [
            'notifications' => Inertia::scroll(fn () => $query
                ->latest()
                ->paginate(15)
                ->through(fn ($notification): array => [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'data' => $notification->data,
                    'read_at' => $notification->read_at?->toISOString(),
                    'created_at' => $notification->created_at->toISOString(),
                ])),
            'filters' => [
                'status' => $status,
                'level' => $level,
            ],
        ]);
    }

    /**
     * Mark a specific notification as read and redirect to action URL.
     */
    public function show(Request $request, string $id): RedirectResponse
    {
        $notification = $request
            ->user()
            ->notifications()
            ->findOrFail($id);

        // Mark as read if unread
        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        // Get action URL from notification data
        $actionUrl = $notification->data['action_url'] ?? null;

        // Validate if action URL exists and is a valid URL
        if ($actionUrl && filter_var($actionUrl, FILTER_VALIDATE_URL)) {
            return redirect($actionUrl);
        }

        // If no valid action URL, redirect back to notifications index
        return to_route('portal.notifications.index');
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request
            ->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return back();
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request
            ->user()
            ->unreadNotifications
            ->markAsRead();

        return back();
    }

    /**
     * Delete a specific notification.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request
            ->user()
            ->notifications()
            ->findOrFail($id)
            ->delete();

        return back();
    }

    /**
     * Delete all notifications.
     */
    public function destroyAll(Request $request): RedirectResponse
    {
        $request->user()->notifications()->delete();

        return back();
    }

    /**
     * Get unread notifications count (for API/AJAX calls).
     */
    public function unreadCount(Request $request): array
    {
        return [
            'count' => $request->user()->unreadNotifications()->count(),
        ];
    }

    /**
     * Get recent notifications (for dropdown).
     */
    public function recent(Request $request): array
    {
        $notifications = $request
            ->user()
            ->notifications()
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($notification): array => [
                'id' => $notification->id,
                'type' => $notification->type,
                'data' => $notification->data,
                'read_at' => $notification->read_at?->toISOString(),
                'created_at' => $notification->created_at->toISOString(),
            ]);

        return [
            'notifications' => $notifications,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ];
    }

    /**
     * Test route to send a notification to the logged-in user.
     */
    public function test(Request $request): string
    {
        $types = ['info', 'success', 'warning', 'error'];
        $type = $types[array_rand($types)];

        $titles = [
            'info' => 'New Information',
            'success' => 'Success!',
            'warning' => 'Warning',
            'error' => 'Error Occurred',
        ];

        $messages = [
            'info' => 'This is a test notification to check if the real-time notification system is working correctly.',
            'success' => 'Your action was completed successfully! The notification system is working perfectly.',
            'warning' => 'Please be aware that this is a test warning notification. Everything is fine.',
            'error' => 'This is a test error notification. No actual error occurred, just testing the system.',
        ];

        $request->user()->notify(new GeneralNotification(
            title: $titles[$type],
            message: $messages[$type],
            actionUrl: route('portal.dashboard'),
            notificationType: $type
        ));

        return 'Send';
    }
}
