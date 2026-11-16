import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { type MonitoringPageProps } from '@/types/monitoring';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Head, router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Cpu,
    Database,
    HardDrive,
    Layers,
    RefreshCw,
    Server,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

export default function MonitoringIndex({
    diagnostics,
    queue,
    workers,
    topTenants,
}: Readonly<MonitoringPageProps>) {
    const { __ } = useLocalizer();
    const [isRefreshing, setIsRefreshing] = useState(false);

    const handleRefresh = () => {
        setIsRefreshing(true);
        router.reload({
            onFinish: () => setIsRefreshing(false),
        });
    };

    const getStatusIcon = (status?: string) => {
        switch (status) {
            case 'ok':
            case 'healthy':
                return <CheckCircle2 className="size-5 text-green-500" />;
            case 'degraded':
                return <AlertTriangle className="size-5 text-yellow-500" />;
            case 'error':
            case 'down':
            case 'unhealthy':
                return <XCircle className="size-5 text-red-500" />;
            default:
                return <Activity className="size-5 text-muted-foreground" />;
        }
    };

    const getStatusBadge = (status?: string) => {
        switch (status) {
            case 'ok':
            case 'healthy':
                return <Badge variant="success">{__('Healthy')}</Badge>;
            case 'degraded':
                return <Badge variant="warning">{__('Degraded')}</Badge>;
            case 'error':
            case 'down':
            case 'unhealthy':
                return <Badge variant="destructive">{__('Error')}</Badge>;
            default:
                return <Badge variant="secondary">{status ?? __('Unknown')}</Badge>;
        }
    };

    const getLatencyBadge = (latencyMs?: number) => {
        if (latencyMs === undefined) return null;
        let variant: 'success' | 'warning' | 'destructive' = 'success';
        if (latencyMs > 200) {
            variant = 'destructive';
        } else if (latencyMs >= 50) {
            variant = 'warning';
        }
        return (
            <Badge variant={variant} className="font-mono text-xs">
                {latencyMs}ms
            </Badge>
        );
    };

    const dbComponent = diagnostics?.components?.database;
    const redisComponent = diagnostics?.components?.redis;
    const minioComponent = diagnostics?.components?.minio;
    const rabbitComponent = diagnostics?.components?.rabbitmq ?? queue;
    const workersComponent = diagnostics?.components?.workers ?? workers;

    const breadcrumbs = [
        {
            title: __('System Monitoring'),
            href: '/panel/admin/monitoring',
        },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={__('System Monitoring')} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {/* Header & Controls */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold tracking-tight">{__('System Monitoring')}</h1>
                            {getStatusBadge(diagnostics?.status)}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {__('Real-time infrastructure health, distributed workers fleet, and tenant usage metrics.')}
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <span className="text-xs text-muted-foreground">
                            {__('Last checked:')} {new Date(diagnostics?.timestamp || Date.now()).toLocaleTimeString()}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleRefresh}
                            disabled={isRefreshing}
                            className="gap-2"
                        >
                            <RefreshCw className={`size-4 ${isRefreshing ? 'animate-spin' : ''}`} />
                            {__('Refresh')}
                        </Button>
                    </div>
                </div>

                {/* Infrastructure Component Status Cards */}
                <div>
                    <h2 className="mb-3 text-lg font-semibold">{__('Infrastructure Services')}</h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                        {/* PostgreSQL Card */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{__('PostgreSQL')}</CardTitle>
                                <Database className="size-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        {getStatusIcon(dbComponent?.status)}
                                        <span className="font-medium text-sm capitalize">{dbComponent?.status ?? 'N/A'}</span>
                                    </div>
                                    {getLatencyBadge(dbComponent?.latency_ms)}
                                </div>
                                {dbComponent?.error && (
                                    <p className="mt-2 text-xs text-red-500 truncate" title={dbComponent.error}>
                                        {dbComponent.error}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {/* Redis Card */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{__('Redis Cache')}</CardTitle>
                                <Server className="size-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        {getStatusIcon(redisComponent?.status)}
                                        <span className="font-medium text-sm capitalize">{redisComponent?.status ?? 'N/A'}</span>
                                    </div>
                                    {getLatencyBadge(redisComponent?.latency_ms)}
                                </div>
                                {redisComponent?.error && (
                                    <p className="mt-2 text-xs text-red-500 truncate" title={redisComponent.error}>
                                        {redisComponent.error}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {/* MinIO Object Storage Card */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{__('MinIO Storage')}</CardTitle>
                                <HardDrive className="size-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        {getStatusIcon(minioComponent?.status)}
                                        <span className="font-medium text-sm capitalize">{minioComponent?.status ?? 'N/A'}</span>
                                    </div>
                                    {getLatencyBadge(minioComponent?.latency_ms)}
                                </div>
                                <p className="mt-2 text-xs text-muted-foreground">
                                    {minioComponent?.buckets ? `${minioComponent.buckets.length} ${__('buckets online')}` : __('Storage verified')}
                                </p>
                            </CardContent>
                        </Card>

                        {/* RabbitMQ Card */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{__('RabbitMQ Queue')}</CardTitle>
                                <Layers className="size-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        {getStatusIcon(rabbitComponent?.status)}
                                        <span className="font-medium text-sm capitalize">{rabbitComponent?.status ?? 'N/A'}</span>
                                    </div>
                                    {getLatencyBadge(rabbitComponent?.latency_ms)}
                                </div>
                                <div className="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                                    <span>{__('Depth:')} <strong className="text-foreground">{rabbitComponent?.queue_depth ?? 0}</strong></span>
                                    <span>{__('Consumers:')} <strong className="text-foreground">{rabbitComponent?.consumers ?? 0}</strong></span>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Workers Fleet Status Card */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{__('Worker Fleet')}</CardTitle>
                                <Cpu className="size-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        {getStatusIcon(workersComponent?.status)}
                                        <span className="font-medium text-sm capitalize">{workersComponent?.status ?? 'N/A'}</span>
                                    </div>
                                    <Badge variant="secondary" className="font-mono text-xs">
                                        {workersComponent?.active_count ?? 0} {__('active')}
                                    </Badge>
                                </div>
                                <p className="mt-2 text-xs text-muted-foreground">
                                    {__('Transcoding nodes online')}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Worker Instances Fleet Table */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>{__('Active Worker Instances')}</CardTitle>
                                <CardDescription>
                                    {__('Registered worker nodes responding to Redis heartbeats')}
                                </CardDescription>
                            </div>
                            <Badge variant="outline">
                                {workersComponent?.instances?.length ?? 0} {__('Nodes')}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {workersComponent?.instances && workersComponent.instances.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{__('Worker ID')}</TableHead>
                                        <TableHead>{__('Hostname')}</TableHead>
                                        <TableHead>{__('Last Heartbeat')}</TableHead>
                                        <TableHead className="text-right">{__('Status')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {workersComponent.instances.map((instance) => (
                                        <TableRow key={instance.id}>
                                            <TableCell className="font-mono text-xs font-semibold">
                                                {instance.id}
                                            </TableCell>
                                            <TableCell className="text-sm">{instance.hostname}</TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {instance.age_seconds}s {__('ago')}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Badge variant="success" className="text-xs">
                                                    {__('Active')}
                                                </Badge>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="py-8 text-center text-sm text-muted-foreground">
                                {__('No worker instances are currently active or reporting heartbeats.')}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Top Tenants Storage Quota Leaderboard */}
                <Card>
                    <CardHeader>
                        <CardTitle>{__('Top Tenants Storage Leaderboard')}</CardTitle>
                        <CardDescription>
                            {__('Tenants consuming the highest volume of video storage compared against assigned quotas')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {topTenants && topTenants.length > 0 ? (
                            <div className="space-y-4">
                                {topTenants.map((tenant, idx) => (
                                    <div key={tenant.id} className="rounded-lg border p-4 space-y-2">
                                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                                            <div className="flex items-center gap-2">
                                                <span className="flex size-6 items-center justify-center rounded-full bg-muted text-xs font-bold">
                                                    {idx + 1}
                                                </span>
                                                <span className="font-semibold">{tenant.name}</span>
                                                <span className="text-xs text-muted-foreground">({tenant.slug})</span>
                                            </div>
                                            <div className="flex items-center gap-2 text-sm">
                                                <span className="font-medium text-foreground">{tenant.formatted_storage}</span>
                                                <span className="text-muted-foreground">/ {tenant.formatted_quota}</span>
                                                <Badge
                                                    variant={tenant.usage_percentage > 90 ? 'destructive' : tenant.usage_percentage > 70 ? 'warning' : 'secondary'}
                                                    className="ml-1"
                                                >
                                                    {tenant.usage_percentage}%
                                                </Badge>
                                            </div>
                                        </div>
                                        <Progress
                                            value={Math.min(tenant.usage_percentage, 100)}
                                            className="h-2"
                                        />
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="py-8 text-center text-sm text-muted-foreground">
                                {__('No tenant storage usage recorded yet.')}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
