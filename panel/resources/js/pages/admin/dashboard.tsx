import { SkeletonCard, StatCard } from '@/components/analytics/stat-card';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AdminLayout from '@/layouts/admin-layout';
import { dashboard } from '@/routes/admin';
import { stats as dashboardStatsRoute } from '@/routes/admin/dashboard';
import { type BreadcrumbItem } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Can } from '@devwizard/laravel-react-permissions';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Award, Shield, TrendingUp, UserCheck, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

interface BasicStats {
    total_users: number;
    active_users: number;
    new_users_today: number;
    verification_rate: number;
    total_roles: number;
    total_permissions: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

export default function Dashboard() {
    const { __ } = useLocalizer();
    const [stats, setStats] = useState<BasicStats | null>(null);
    const [loading, setLoading] = useState(true);
    const fetchBasicStats = async () => {
        try {
            setLoading(true);
            const response = await axios.get(dashboardStatsRoute().url);
            setStats(response.data);
        } catch (error) {
            console.error('Failed to fetch dashboard stats:', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchBasicStats();
    }, []);

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Dashboard')} />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold">{__('Dashboard')}</h1>
                    <p className="text-muted-foreground">{__("Welcome back! Here's an overview of your application.")}</p>
                </div>

                {/* Overview Stats */}
                <div className="grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {loading ? (
                        Array.from({ length: 4 }, (_, i) => <SkeletonCard key={i} />)
                    ) : stats ? (
                        <>
                            <Can permission="users.view">
                                <StatCard title={__('Total Users')} value={stats.total_users} icon={<Users className="h-6 w-6" />} />
                                <StatCard
                                    title={__('Active Users')}
                                    value={stats.active_users}
                                    description={__(':percentage% verified', { percentage: stats.verification_rate })}
                                    icon={<UserCheck className="h-6 w-6" />}
                                />
                                <StatCard title={__('New Today')} value={stats.new_users_today} icon={<TrendingUp className="h-6 w-6" />} />
                            </Can>
                            <Can permission="roles.view">
                                <StatCard title={__('Total Roles')} value={stats.total_roles} icon={<Shield className="h-6 w-6" />} />
                            </Can>
                        </>
                    ) : null}
                </div>

                {/* Quick Actions */}
                <div className="grid gap-6 md:grid-cols-2">
                    <Can permission="users.view">
                        <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                            <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/10 dark:stroke-neutral-100/10" />
                            <div className="relative">
                                <div className="mb-4 flex items-center gap-3">
                                    <div className="rounded-lg bg-blue-500/10 p-2">
                                        <Users className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <h3 className="font-semibold">{__('User Management')}</h3>
                                </div>
                                <p className="mb-4 text-sm text-muted-foreground">
                                    {__('Manage users, permissions, and access controls for your application.')}
                                </p>
                                <div className="text-sm font-medium text-blue-600 dark:text-blue-400">{__('View detailed reports →')}</div>
                            </div>
                        </div>
                    </Can>

                    <Can permission="roles.view">
                        <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                            <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/10 dark:stroke-neutral-100/10" />
                            <div className="relative">
                                <div className="mb-4 flex items-center gap-3">
                                    <div className="rounded-lg bg-green-500/10 p-2">
                                        <Shield className="h-5 w-5 text-green-600 dark:text-green-400" />
                                    </div>
                                    <h3 className="font-semibold">{__('Role Management')}</h3>
                                </div>
                                <p className="mb-4 text-sm text-muted-foreground">
                                    {__('Configure roles and permissions to control access across your system.')}
                                </p>
                                <div className="text-sm font-medium text-green-600 dark:text-green-400">{__('View detailed reports →')}</div>
                            </div>
                        </div>
                    </Can>
                </div>

                {/* Recent Activity Placeholder */}
                <Can permission="users.view">
                    <div className="space-y-4">
                        <h2 className="text-lg font-semibold">{__('Recent Activity')}</h2>
                        <div className="relative min-h-[200px] overflow-hidden rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                            <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/10 dark:stroke-neutral-100/10" />
                            <div className="relative flex h-full items-center justify-center">
                                <div className="text-center">
                                    <Award className="mx-auto mb-3 h-8 w-8 text-muted-foreground" />
                                    <h3 className="mb-1 font-medium text-muted-foreground">{__('Activity Feed Coming Soon')}</h3>
                                    <p className="text-sm text-muted-foreground">
                                        {__('Track user registrations, role changes, and system events.')}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </Can>
            </div>
        </AdminLayout>
    );
}
