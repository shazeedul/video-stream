import { Badge } from '@/components/ui/badge';
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
import SettingsLayout from '@/pages/portal/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { type UsagePageProps } from '@/types/usage';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    BarChart3,
    Film,
    HardDrive,
    Image as ImageIcon,
    Layers,
    Play,
} from 'lucide-react';

export default function UsagePage({
    tenant,
    storage,
    breakdown,
    trends,
}: Readonly<UsagePageProps>) {
    const { __ } = useLocalizer();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('Settings'),
            href: '/panel/settings/profile',
        },
        {
            title: __('Usage & Storage'),
            href: '/panel/settings/usage',
        },
    ];

    return (
        <SettingsLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Storage & Usage')} />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h2 className="text-xl font-bold tracking-tight">{__('Storage & Usage')}</h2>
                    <p className="text-sm text-muted-foreground">
                        {__('Monitor storage capacity, asset breakdown, and streaming egress for')} {tenant.name}.
                    </p>
                </div>

                {/* Storage Quota Progress Card */}
                <Card>
                    <CardHeader className="pb-3">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div>
                                <CardTitle className="text-base flex items-center gap-2">
                                    <HardDrive className="size-5 text-primary" />
                                    {__('Storage Capacity')}
                                </CardTitle>
                                <CardDescription>
                                    {__('Current disk space utilized across all video assets')}
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="text-sm font-semibold">
                                    {storage.formatted_used} {__('of')} {storage.formatted_quota} {__('used')}
                                </span>
                                <Badge
                                    variant={storage.is_exceeded ? 'destructive' : storage.usage_percentage > 80 ? 'warning' : 'secondary'}
                                >
                                    {storage.usage_percentage}%
                                </Badge>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <Progress
                            value={Math.min(storage.usage_percentage, 100)}
                            className="h-3"
                        />
                        {storage.is_exceeded && (
                            <div className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                                <AlertTriangle className="size-4 shrink-0 text-red-600" />
                                <span>
                                    {__('Your organization has exceeded its assigned storage quota. New video uploads may be restricted.')}
                                </span>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Breakdown by Asset Type Cards */}
                <div>
                    <h3 className="mb-3 text-base font-semibold">{__('Storage Breakdown by Asset Type')}</h3>
                    <div className="grid gap-4 md:grid-cols-3">
                        {/* Original Videos */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{__('Original Source Videos')}</CardTitle>
                                <Film className="size-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{breakdown.original.formatted}</div>
                                <div className="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                                    <span>{breakdown.original.count} {__('files')}</span>
                                    <span>{breakdown.original.percentage}% {__('of storage')}</span>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Transcoded HLS */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{__('Transcoded HLS Streams')}</CardTitle>
                                <Layers className="size-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{breakdown.hls.formatted}</div>
                                <div className="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                                    <span>{breakdown.hls.count} {__('manifests & segments')}</span>
                                    <span>{breakdown.hls.percentage}% {__('of storage')}</span>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Thumbnails */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{__('Thumbnails & Previews')}</CardTitle>
                                <ImageIcon className="size-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{breakdown.thumbnail.formatted}</div>
                                <div className="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                                    <span>{breakdown.thumbnail.count} {__('images')}</span>
                                    <span>{breakdown.thumbnail.percentage}% {__('of storage')}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* 30-Day Metrics Summary */}
                <div>
                    <h3 className="mb-3 text-base font-semibold">{__('30-Day Activity & Delivery')}</h3>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{__('30-Day Video Plays')}</CardTitle>
                                <Play className="size-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{trends.total_views_30d.toLocaleString()}</div>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {__('Total playback sessions initiated')}
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{__('30-Day Estimated Egress')}</CardTitle>
                                <BarChart3 className="size-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{trends.formatted_egress_30d}</div>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {__('Bandwidth consumed during video streaming')}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* 30-Day Trends Table */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{__('Recent Daily Usage History')}</CardTitle>
                        <CardDescription>
                            {__('Daily breakdown of playback traffic and storage footprint over the past 30 days')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {trends.records && trends.records.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{__('Date')}</TableHead>
                                        <TableHead className="text-right">{__('Views')}</TableHead>
                                        <TableHead className="text-right">{__('Streaming Egress')}</TableHead>
                                        <TableHead className="text-right">{__('Storage Footprint')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {trends.records.map((record) => (
                                        <TableRow key={record.date}>
                                            <TableCell className="font-medium text-sm">
                                                {record.date}
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-xs">
                                                {record.view_count.toLocaleString()}
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-xs">
                                                {record.formatted_egress}
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-xs">
                                                {record.formatted_storage}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="py-8 text-center text-sm text-muted-foreground">
                                {__('No recorded usage metrics for the last 30 days.')}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    );
}
