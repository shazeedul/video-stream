import notificationSound from '@/assets/sounds/notification.mp3';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useAuth } from '@/hooks/auth';
import { useAdminNotifications } from '@/hooks/use-admin-notifications';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import { type Notification } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { router } from '@inertiajs/react';
import { useEchoNotification } from '@laravel/echo-react';
import { formatDistanceToNow } from 'date-fns';
import { AlertCircle, AlertTriangle, Bell, CheckCheck, CheckCircle, Info, RefreshCw, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

const notifications = admin.notifications;

const notificationIcons = {
    info: Info,
    success: CheckCircle,
    warning: AlertTriangle,
    error: AlertCircle,
};

export function NotificationDropdown() {
    const { user } = useAuth();
    const { __ } = useLocalizer();
    const {
        notifications: recentNotifications,
        unreadCount,
        addNotification,
        markAllAsRead,
        markAsRead,
        removeNotification,
        refresh,
        isLoading,
    } = useAdminNotifications();
    const [isOpen, setIsOpen] = useState(false);
    const audioRef = useRef<HTMLAudioElement>(null);

    // Set audio volume on mount
    useEffect(() => {
        if (audioRef.current) {
            audioRef.current.volume = 0.5;
        }
    }, []);

    // Subscribe to the user's notification channel using Laravel Echo
    useEchoNotification(user ? `App.Models.User.${user.id}` : '', (notification: Record<string, unknown>) => {
        const newNotification: Notification = {
            id: notification.id as string,
            type: notification.type as string,
            data: notification as unknown as Notification['data'],
            read_at: null,
            created_at: new Date().toISOString(),
        };

        // Add to cache via hook
        addNotification(newNotification);

        // Play notification sound
        audioRef.current?.play().catch((error) => console.log('Audio play failed:', error));

        // Show toast notification
        const NotificationToast = () => {
            const IconComponent = notificationIcons[newNotification.data.level] || Info;
            return (
                <div
                    className="group relative flex w-full cursor-pointer items-start gap-4 rounded-xl border bg-card p-4 shadow-xl transition-all hover:shadow-2xl"
                    onClick={() => {
                        // Mark as read in cache immediately
                        markAsRead(newNotification.id);
                        router.visit(notifications.show(newNotification.id).url);
                    }}
                >
                    {/* Accent line based on notification level */}
                    <div
                        className={cn(
                            'absolute top-0 left-0 h-full w-1 rounded-l-xl',
                            newNotification.data.level === 'success' && 'bg-green-500',
                            newNotification.data.level === 'error' && 'bg-red-500',
                            newNotification.data.level === 'warning' && 'bg-yellow-500',
                            newNotification.data.level === 'info' && 'bg-blue-500',
                        )}
                    />

                    {newNotification.data.icon ? (
                        <img
                            src={newNotification.data.icon}
                            alt=""
                            className="mt-0.5 h-12 w-12 flex-shrink-0 rounded-full object-cover ring-2 ring-border"
                        />
                    ) : (
                        <div
                            className={cn(
                                'mt-0.5 flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full',
                                newNotification.data.level === 'success' && 'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400',
                                newNotification.data.level === 'error' && 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400',
                                newNotification.data.level === 'warning' &&
                                    'bg-yellow-100 text-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-400',
                                newNotification.data.level === 'info' && 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
                            )}
                        >
                            <IconComponent className="h-6 w-6" />
                        </div>
                    )}
                    <div className="flex-1 space-y-1 pr-2">
                        <p className="font-semibold text-card-foreground">{newNotification.data.title}</p>
                        <p className="text-sm leading-relaxed text-muted-foreground">{newNotification.data.message}</p>
                    </div>
                </div>
            );
        };

        toast.custom(() => <NotificationToast />, {
            duration: 5000,
            position: 'bottom-right',
        });
    });

    const handleMarkAllAsRead = () => {
        router.post(
            notifications.markAllRead().url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    markAllAsRead();
                },
            },
        );
    };

    const handleDeleteNotification = (notificationId: string) => {
        router.delete(notifications.destroy(notificationId).url, {
            preserveScroll: true,
            onSuccess: () => {
                removeNotification(notificationId);
            },
        });
    };

    const handleNotificationClick = (notification: Notification) => {
        // Mark as read in cache immediately for instant UI update
        if (!notification.read_at) {
            markAsRead(notification.id);
        }

        router.visit(notifications.show(notification.id).url);
        setIsOpen(false);
    };

    return (
        <>
            <audio ref={audioRef} className="hidden" preload="auto">
                <source src={notificationSound} type="audio/mpeg" />
            </audio>
            <DropdownMenu open={isOpen} onOpenChange={setIsOpen}>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon" className="relative h-9 w-9 cursor-pointer p-0">
                        <Bell className="h-5 w-5" />
                        {unreadCount > 0 && (
                            <span className="absolute top-0 right-0 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
                                {unreadCount > 9 ? '9+' : unreadCount}
                            </span>
                        )}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-80 p-0">
                    <div className="flex items-center justify-between border-b px-4 py-3">
                        <h3 className="font-semibold">{__('Notifications')}</h3>
                        <div className="flex items-center gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={refresh}
                                disabled={isLoading}
                                className="h-8 w-8 cursor-pointer"
                                title={__('Refresh notifications')}
                            >
                                <RefreshCw className={cn('h-4 w-4', isLoading && 'animate-spin')} />
                            </Button>
                            {unreadCount > 0 && (
                                <Button variant="ghost" size="sm" onClick={handleMarkAllAsRead} className="h-8 cursor-pointer text-xs">
                                    <CheckCheck className="mr-1 h-4 w-4" />
                                    {__('Mark all read')}
                                </Button>
                            )}
                        </div>
                    </div>

                    <ScrollArea className="h-[400px]">
                        {recentNotifications.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-8 text-center">
                                <Bell className="mb-2 h-12 w-12 text-muted-foreground/50" />
                                <p className="text-sm text-muted-foreground">{__('No notifications yet')}</p>
                            </div>
                        ) : (
                            <div className="divide-y">
                                {recentNotifications.map((notification) => {
                                    const IconComponent = notificationIcons[notification.data.level] || Info;
                                    return (
                                        <div
                                            key={notification.id}
                                            className={cn(
                                                'group relative flex gap-3 px-4 py-3 transition-colors hover:bg-accent',
                                                !notification.read_at && 'bg-blue-50/50 dark:bg-blue-950/20',
                                            )}
                                        >
                                            <div className="flex flex-1 cursor-pointer gap-3" onClick={() => handleNotificationClick(notification)}>
                                                {notification.data.icon ? (
                                                    <img
                                                        src={notification.data.icon}
                                                        alt=""
                                                        className="h-10 w-10 flex-shrink-0 rounded-full object-cover"
                                                    />
                                                ) : (
                                                    <div
                                                        className={cn(
                                                            'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full',
                                                            notification.data.level === 'success' &&
                                                                'bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-400',
                                                            notification.data.level === 'error' &&
                                                                'bg-red-100 text-red-600 dark:bg-red-900 dark:text-red-400',
                                                            notification.data.level === 'warning' &&
                                                                'bg-yellow-100 text-yellow-600 dark:bg-yellow-900 dark:text-yellow-400',
                                                            notification.data.level === 'info' &&
                                                                'bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-400',
                                                        )}
                                                    >
                                                        <IconComponent className="h-5 w-5" />
                                                    </div>
                                                )}
                                                <div className="flex-1 space-y-1">
                                                    <p className="text-sm font-medium">{notification.data.title}</p>
                                                    <p className="line-clamp-2 text-xs text-muted-foreground">{notification.data.message}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {formatDistanceToNow(new Date(notification.created_at), { addSuffix: true })}
                                                    </p>
                                                </div>
                                            </div>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="h-8 w-8 opacity-0 transition-opacity group-hover:opacity-100"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    handleDeleteNotification(notification.id);
                                                }}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </ScrollArea>

                    {recentNotifications.length > 0 && (
                        <>
                            <DropdownMenuSeparator className="m-0" />
                            <DropdownMenuItem asChild>
                                <Button
                                    variant="ghost"
                                    className="w-full cursor-pointer rounded-none"
                                    onClick={() => {
                                        router.visit(notifications.index().url);
                                        setIsOpen(false);
                                    }}
                                >
                                    {__('View all notifications')}
                                </Button>
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
        </>
    );
}
