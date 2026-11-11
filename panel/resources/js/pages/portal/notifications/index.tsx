import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import PortalLayout from '@/layouts/portal-layout';
import { cn } from '@/lib/utils';
import portal from '@/routes/portal';
import type { BreadcrumbItem, Notification, PaginatedData } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Head, InfiniteScroll, router } from '@inertiajs/react';
import { formatDistanceToNow } from 'date-fns';
import { AlertCircle, AlertTriangle, Bell, CheckCheck, CheckCircle, Info, Trash2 } from 'lucide-react';

const notifications = portal.notifications;

interface NotificationsIndexProps {
    notifications: PaginatedData<Notification>;
    filters: { status?: string; level?: string };
}

const notificationIcons = {
    info: Info,
    success: CheckCircle,
    warning: AlertTriangle,
    error: AlertCircle,
};

export default function NotificationsIndex({ notifications: notificationsData, filters }: NotificationsIndexProps) {
    const { __ } = useLocalizer();
    const breadcrumbItems: BreadcrumbItem[] = [
        { title: __('Dashboard'), href: portal.dashboard().url },
        { title: __('Notifications'), href: notifications.index().url },
    ];

    const handleFilterChange = (key: 'status' | 'level', value: string) => {
        router.get(
            notifications.index().url,
            {
                ...filters,
                [key]: value === 'all' ? undefined : value,
            },
            {
                preserveState: false,
                preserveScroll: false,
            },
        );
    };

    const handleMarkAllAsRead = () => {
        router.post(
            notifications.markAllRead().url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    router.reload({ only: ['notifications'] });
                },
            },
        );
    };

    const handleDelete = (notificationId: string, e: React.MouseEvent) => {
        e.stopPropagation();

        router.delete(notifications.destroy(notificationId).url, {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['notifications'] });
            },
        });
    };

    const handleMarkAsRead = (notificationId: string, e: React.MouseEvent) => {
        e.stopPropagation();

        router.patch(
            notifications.markAsRead(notificationId).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    router.reload({ only: ['notifications'] });
                },
            },
        );
    };

    const handleDeleteFiltered = () => {
        router.delete(notifications.index().url, {
            data: filters,
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['notifications'] });
            },
        });
    };

    const handleCardClick = (notification: Notification) => {
        // Use the show route which handles marking as read and redirecting
        router.visit(notifications.show(notification.id).url);
    };

    const unreadCount = notificationsData.data.filter((n) => !n.read_at).length;

    return (
        <PortalLayout breadcrumbs={breadcrumbItems}>
            <Head title={__('Notifications')} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading title={__('Notifications')} />
                    <div className="flex gap-2">
                        {unreadCount > 0 && (
                            <Button variant="outline" size="sm" onClick={handleMarkAllAsRead} className="cursor-pointer">
                                <CheckCheck className="mr-2 size-4" />
                                {__('Mark All as Read')}
                            </Button>
                        )}
                        {notificationsData.data.length > 0 && (
                            <Button variant="destructive" size="sm" onClick={handleDeleteFiltered} className="cursor-pointer">
                                <Trash2 className="mr-2 size-4" />
                                {__('Delete Filtered')}
                            </Button>
                        )}
                    </div>
                </div>

                {/* Filters */}
                <Card className="p-4">
                    <div className="flex flex-wrap gap-4">
                        <div className="space-y-2">
                            <label className="text-sm font-medium">{__('Status')}</label>
                            <Tabs value={filters.status || 'all'} onValueChange={(value) => handleFilterChange('status', value)}>
                                <TabsList>
                                    <TabsTrigger className="cursor-pointer" value="all">
                                        {__('All')}
                                    </TabsTrigger>
                                    <TabsTrigger className="cursor-pointer" value="unread">
                                        {__('Unread')} {unreadCount > 0 && <span className="ml-1 text-xs">({unreadCount})</span>}
                                    </TabsTrigger>
                                    <TabsTrigger className="cursor-pointer" value="read">
                                        {__('Read')}
                                    </TabsTrigger>
                                </TabsList>
                            </Tabs>
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">{__('Level')}</label>
                            <Tabs value={filters.level || 'all'} onValueChange={(value) => handleFilterChange('level', value)}>
                                <TabsList>
                                    <TabsTrigger className="cursor-pointer" value="all">
                                        {__('All')}
                                    </TabsTrigger>
                                    <TabsTrigger className="cursor-pointer" value="info">
                                        {__('Info')}
                                    </TabsTrigger>
                                    <TabsTrigger className="cursor-pointer" value="success">
                                        {__('Success')}
                                    </TabsTrigger>
                                    <TabsTrigger className="cursor-pointer" value="warning">
                                        {__('Warning')}
                                    </TabsTrigger>
                                    <TabsTrigger className="cursor-pointer" value="error">
                                        {__('Error')}
                                    </TabsTrigger>
                                </TabsList>
                            </Tabs>
                        </div>
                    </div>
                </Card>

                {/* Notifications List */}
                <div className="space-y-3">
                    {notificationsData.data.length === 0 ? (
                        <Card className="p-12">
                            <div className="flex flex-col items-center justify-center text-center">
                                <Bell className="mb-4 size-12 text-muted-foreground" />
                                <h3 className="mb-1 text-lg font-semibold">{__('No notifications')}</h3>
                                <p className="text-sm text-muted-foreground">
                                    {filters.status === 'unread'
                                        ? __("You don't have any unread notifications")
                                        : __("You don't have any notifications matching your filters.")}
                                </p>
                            </div>
                        </Card>
                    ) : (
                        <InfiniteScroll data="notifications">
                            {notificationsData.data.map((notification) => {
                                if (!notification.data) return null;

                                const IconComponent = notificationIcons[notification.data.level] || Info;

                                return (
                                    <Card
                                        key={notification.id}
                                        className={cn(
                                            'group relative mb-3 cursor-pointer transition-all hover:shadow-md',
                                            !notification.read_at && 'border-l-4 border-l-blue-500 bg-blue-50/50 dark:bg-blue-950/20',
                                        )}
                                        onClick={() => handleCardClick(notification)}
                                    >
                                        <div className="flex gap-4 p-4">
                                            {notification.data.icon ? (
                                                <img
                                                    src={notification.data.icon}
                                                    alt=""
                                                    className="size-12 flex-shrink-0 rounded-full object-cover"
                                                />
                                            ) : (
                                                <div
                                                    className={cn(
                                                        'flex size-12 flex-shrink-0 items-center justify-center rounded-full',
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
                                                    <IconComponent className="size-6" />
                                                </div>
                                            )}
                                            <div className="flex-1 space-y-1">
                                                <div className="flex items-start justify-between gap-2">
                                                    <h3 className="font-semibold">{notification.data.title}</h3>
                                                    <div className="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                                        {!notification.read_at && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={(e) => handleMarkAsRead(notification.id, e)}
                                                                className="h-8 cursor-pointer"
                                                            >
                                                                <CheckCircle className="mr-1 size-4" />
                                                                {__('Mark as read')}
                                                            </Button>
                                                        )}
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={(e) => handleDelete(notification.id, e)}
                                                            className="size-8 cursor-pointer"
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </Button>
                                                    </div>
                                                </div>
                                                <p className="text-sm text-muted-foreground">{notification.data.message}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatDistanceToNow(new Date(notification.created_at), { addSuffix: true })}
                                                </p>
                                            </div>
                                        </div>
                                    </Card>
                                );
                            })}
                        </InfiniteScroll>
                    )}
                </div>
            </div>
        </PortalLayout>
    );
}
