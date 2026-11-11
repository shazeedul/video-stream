import admin from '@/routes/admin';
import type { Notification } from '@/types';
import axios from 'axios';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useAuth } from './auth';

interface NotificationsData {
    notifications: Notification[];
    unread_count: number;
}

interface CachedNotifications extends NotificationsData {
    userId: number;
    expiresAt: number;
}

const CACHE_KEY = 'notifications_cache';
const CACHE_DURATION = 5 * 60 * 1000; // 5 minutes in milliseconds

/**
 * Hook to manage notifications with localStorage caching.
 *
 * Features:
 * - Caches notifications in localStorage with 5-minute expiry
 * - Auto-refreshes when cache expires
 * - Validates user ID to prevent mismatch when switching accounts
 * - Provides methods to add new notifications (from WebSocket)
 * - Periodic background refresh every 5 minutes
 */
export function useAdminNotifications() {
    const { user } = useAuth();
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [unreadCount, setUnreadCount] = useState<number>(0);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);

    // Use refs to track latest values for cache updates
    const notificationsRef = useRef<Notification[]>([]);
    const unreadCountRef = useRef<number>(0);

    // Keep refs in sync with state
    useEffect(() => {
        notificationsRef.current = notifications;
    }, [notifications]);

    useEffect(() => {
        unreadCountRef.current = unreadCount;
    }, [unreadCount]);

    /**
     * Get cached notifications from localStorage
     */
    const getCachedNotifications = useCallback((): CachedNotifications | null => {
        if (!user) return null;

        try {
            const cached = localStorage.getItem(CACHE_KEY);
            if (!cached) return null;

            const data: CachedNotifications = JSON.parse(cached);

            // Validate user ID
            if (data.userId !== user.id) {
                localStorage.removeItem(CACHE_KEY);
                return null;
            }

            // Check if expired
            if (Date.now() > data.expiresAt) {
                localStorage.removeItem(CACHE_KEY);
                return null;
            }

            return data;
        } catch (err) {
            console.error('Error reading cached notifications:', err);
            localStorage.removeItem(CACHE_KEY);
            return null;
        }
    }, [user]);

    /**
     * Save notifications to localStorage
     */
    const setCachedNotifications = useCallback(
        (data: NotificationsData) => {
            if (!user) return;

            try {
                const cached: CachedNotifications = {
                    ...data,
                    userId: user.id,
                    expiresAt: Date.now() + CACHE_DURATION,
                };

                localStorage.setItem(CACHE_KEY, JSON.stringify(cached));
            } catch (err) {
                console.error('Error caching notifications:', err);
            }
        },
        [user],
    );

    /**
     * Fetch notifications from API
     */
    const fetchNotifications = useCallback(async () => {
        if (!user) {
            setNotifications([]);
            setUnreadCount(0);
            setIsLoading(false);
            return;
        }

        try {
            setError(null);
            const response = await axios.get<NotificationsData>(admin.notifications.recent().url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = response.data;

            setNotifications(data.notifications);
            setUnreadCount(data.unread_count);
            setCachedNotifications(data);
        } catch (err) {
            console.error('Error fetching notifications:', err);
            setError('Failed to load notifications');
        } finally {
            setIsLoading(false);
        }
    }, [user, setCachedNotifications]);

    /**
     * Add a new notification (e.g., from WebSocket)
     */
    const addNotification = useCallback(
        (notification: Notification) => {
            setNotifications((prev) => {
                const updated = [notification, ...prev.slice(0, 4)];
                return updated;
            });

            setUnreadCount((prev) => prev + 1);

            // Update cache using refs to get the latest values
            setTimeout(() => {
                if (user) {
                    setCachedNotifications({
                        notifications: notificationsRef.current,
                        unread_count: unreadCountRef.current,
                    });
                }
            }, 0);
        },
        [user, setCachedNotifications],
    );

    /**
     * Mark all as read
     */
    const markAllAsRead = useCallback(() => {
        setNotifications((prev) =>
            prev.map((n) => ({
                ...n,
                read_at: n.read_at || new Date().toISOString(),
            })),
        );
        setUnreadCount(0);

        // Update cache using refs
        setTimeout(() => {
            if (user) {
                setCachedNotifications({
                    notifications: notificationsRef.current,
                    unread_count: 0,
                });
            }
        }, 0);
    }, [user, setCachedNotifications]);

    /**
     * Mark a single notification as read
     */
    const markAsRead = useCallback(
        (notificationId: string) => {
            const notification = notificationsRef.current.find((n) => n.id === notificationId);

            // Only update if notification exists and is unread
            if (!notification || notification.read_at) {
                return;
            }

            setNotifications((prev) => prev.map((n) => (n.id === notificationId ? { ...n, read_at: new Date().toISOString() } : n)));

            setUnreadCount((prev) => Math.max(0, prev - 1));

            // Update cache using refs
            setTimeout(() => {
                if (user) {
                    setCachedNotifications({
                        notifications: notificationsRef.current,
                        unread_count: unreadCountRef.current,
                    });
                }
            }, 0);
        },
        [user, setCachedNotifications],
    );

    /**
     * Remove a notification
     */
    const removeNotification = useCallback(
        (notificationId: string) => {
            const removedNotification = notificationsRef.current.find((n) => n.id === notificationId);

            setNotifications((prev) => prev.filter((n) => n.id !== notificationId));

            if (removedNotification && !removedNotification.read_at) {
                setUnreadCount((prev) => Math.max(0, prev - 1));
            }

            // Update cache using refs
            setTimeout(() => {
                if (user) {
                    setCachedNotifications({
                        notifications: notificationsRef.current,
                        unread_count: unreadCountRef.current,
                    });
                }
            }, 0);
        },
        [user, setCachedNotifications],
    );

    /**
     * Refresh notifications (force fetch)
     */
    const refresh = useCallback(() => {
        setIsLoading(true);
        fetchNotifications();
    }, [fetchNotifications]);

    // Initial load: try cache first, then fetch
    useEffect(() => {
        if (!user) {
            setNotifications([]);
            setUnreadCount(0);
            setIsLoading(false);
            return;
        }

        const cached = getCachedNotifications();

        if (cached) {
            // Use cached data
            setNotifications(cached.notifications);
            setUnreadCount(cached.unread_count);
            setIsLoading(false);
        } else {
            // Fetch fresh data
            fetchNotifications();
        }
    }, [user, getCachedNotifications, fetchNotifications]);

    // Set up periodic refresh every 5 minutes
    useEffect(() => {
        if (!user) return;

        const interval = setInterval(() => {
            fetchNotifications();
        }, CACHE_DURATION);

        return () => clearInterval(interval);
    }, [user, fetchNotifications]);

    return {
        notifications,
        unreadCount,
        isLoading,
        error,
        addNotification,
        markAllAsRead,
        markAsRead,
        removeNotification,
        refresh,
    };
}
