import DeleteConfirmationDialog from '@/components/delete-confirmation-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import VideoStatusBadge from '@/pages/portal/videos/partials/video-status-badge';
import { dashboard } from '@/routes/admin';
import { destroy as videosDestroy, index as videosIndex } from '@/routes/admin/videos';
import { type BreadcrumbItem, type UploadSession, type Video, type VideoAsset } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Can } from '@devwizard/laravel-react-permissions';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    Clock,
    Copy,
    Database,
    EyeOff,
    FileCode,
    Film,
    Globe,
    HardDrive,
    Layers,
    Lock,
    Trash2,
    UploadCloud,
    User as UserIcon,
} from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';

interface Props {
    video: Video;
}

export function formatBytes(bytes: number | null | undefined): string {
    if (bytes === null || bytes === undefined || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    const val = (bytes / Math.pow(k, i)).toFixed(2);
    return `${val} ${sizes[i]}`;
}

export function formatDuration(seconds: number | null | undefined): string {
    if (seconds === null || seconds === undefined || seconds <= 0) {
        return '—';
    }
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.floor(seconds % 60);

    if (h > 0) {
        return `${h}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
    }
    return `${m}:${s.toString().padStart(2, '0')}`;
}

export default function Show({ video }: Props) {
    const { __ } = useLocalizer();

    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('Admin'),
            href: dashboard().url,
        },
        {
            title: __('Videos'),
            href: videosIndex().url,
        },
        {
            title: video.title,
            href: '',
        },
    ];

    const copyToClipboard = (text: string, label: string) => {
        navigator.clipboard.writeText(text);
        toast.success(__(':label copied to clipboard', { label }));
    };

    const handleDeleteVideo = () => {
        setIsDeleting(true);
        router.delete(videosDestroy({ video: video.id }).url, {
            onSuccess: () => {
                setDeleteDialogOpen(false);
                setIsDeleting(false);
            },
            onError: () => {
                setIsDeleting(false);
                toast.error(__('Failed to delete video.'));
            },
        });
    };

    const renderVisibilityBadge = (visibility: string) => {
        switch (visibility) {
            case 'public':
                return (
                    <Badge variant="outline" className="border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 gap-1 font-medium">
                        <Globe className="h-3 w-3" />
                        <span>{__('Public')}</span>
                    </Badge>
                );
            case 'unlisted':
                return (
                    <Badge variant="outline" className="border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400 gap-1 font-medium">
                        <EyeOff className="h-3 w-3" />
                        <span>{__('Unlisted')}</span>
                    </Badge>
                );
            case 'private':
            default:
                return (
                    <Badge variant="outline" className="border-border text-muted-foreground gap-1 font-medium">
                        <Lock className="h-3 w-3" />
                        <span>{__('Private')}</span>
                    </Badge>
                );
        }
    };

    const renderSessionStatusBadge = (status: string) => {
        switch (status) {
            case 'completed':
                return (
                    <Badge variant="outline" className="border-emerald-500/20 bg-emerald-500/10 text-emerald-600 font-medium">
                        {__('Completed')}
                    </Badge>
                );
            case 'pending':
                return (
                    <Badge variant="outline" className="border-blue-500/20 bg-blue-500/10 text-blue-600 font-medium">
                        {__('Pending')}
                    </Badge>
                );
            case 'aborted':
                return <Badge variant="destructive">{__('Aborted')}</Badge>;
            case 'expired':
                return <Badge variant="secondary">{__('Expired')}</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    const uploadSessions: UploadSession[] = video.upload_sessions ?? video.uploadSessions ?? [];
    const assets: VideoAsset[] = video.assets ?? [];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={video.title} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                {/* Back Link & Header */}
                <div className="flex flex-col gap-4">
                    <div>
                        <Link href={videosIndex().url}>
                            <Button variant="ghost" size="sm" className="gap-1.5 text-muted-foreground hover:text-foreground">
                                <ArrowLeft className="h-4 w-4" />
                                {__('Back to Videos')}
                            </Button>
                        </Link>
                    </div>

                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-start gap-4">
                            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <Film className="h-6 w-6" />
                            </div>
                            <div className="space-y-1.5">
                                <h1 className="text-2xl font-bold tracking-tight text-foreground">{video.title}</h1>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="outline" className="inline-flex items-center gap-1.5 font-normal">
                                        <Building2 className="h-3.5 w-3.5 text-muted-foreground" />
                                        <span>{video.tenant?.name ?? video.tenant_id}</span>
                                    </Badge>
                                    <VideoStatusBadge status={video.status} />
                                    {renderVisibilityBadge(video.visibility)}
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            <Can permission="videos.delete">
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => setDeleteDialogOpen(true)}
                                    className="cursor-pointer"
                                >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    {__('Delete Video')}
                                </Button>
                            </Can>
                        </div>
                    </div>
                </div>

                {/* Technical Properties Grid */}
                <Card>
                    <CardHeader className="border-b bg-muted/20 pb-4">
                        <div className="flex items-center gap-2">
                            <Database className="h-4 w-4 text-primary" />
                            <CardTitle className="text-base font-semibold">{__('Technical Properties')}</CardTitle>
                        </div>
                        <CardDescription>
                            {__('Core database record attributes and storage identifiers')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-6">
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                            {/* Video ID */}
                            <div className="space-y-1">
                                <div className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    {__('Video ID')}
                                </div>
                                <div className="flex items-center gap-1.5 font-mono text-sm font-medium text-foreground">
                                    <span className="truncate">{video.id}</span>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        className="h-6 w-6 shrink-0 cursor-pointer text-muted-foreground hover:text-foreground"
                                        onClick={() => copyToClipboard(video.id, __('Video ID'))}
                                        title={__('Copy Video ID')}
                                    >
                                        <Copy className="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </div>

                            {/* Tenant ID */}
                            <div className="space-y-1">
                                <div className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    {__('Tenant ID')}
                                </div>
                                <div className="flex items-center gap-1.5 font-mono text-sm font-medium text-foreground">
                                    <span className="truncate">{video.tenant_id}</span>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        className="h-6 w-6 shrink-0 cursor-pointer text-muted-foreground hover:text-foreground"
                                        onClick={() => copyToClipboard(video.tenant_id, __('Tenant ID'))}
                                        title={__('Copy Tenant ID')}
                                    >
                                        <Copy className="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                                {video.tenant?.name && (
                                    <div className="text-xs text-muted-foreground">{video.tenant.name}</div>
                                )}
                            </div>

                            {/* Uploader */}
                            <div className="space-y-1">
                                <div className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    {__('Uploader')}
                                </div>
                                <div className="flex items-center gap-1.5 text-sm font-medium text-foreground">
                                    <UserIcon className="h-4 w-4 text-muted-foreground" />
                                    <span>{video.user?.name ?? __('System')}</span>
                                </div>
                                {video.user?.email && (
                                    <div className="text-xs text-muted-foreground">{video.user.email}</div>
                                )}
                            </div>

                            {/* Duration */}
                            <div className="space-y-1">
                                <div className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    {__('Duration')}
                                </div>
                                <div className="flex items-center gap-1.5 text-sm font-medium text-foreground">
                                    <Clock className="h-4 w-4 text-muted-foreground" />
                                    <span>{formatDuration(video.duration_seconds)}</span>
                                </div>
                                {video.duration_seconds !== null && video.duration_seconds !== undefined && (
                                    <div className="text-xs text-muted-foreground">
                                        {video.duration_seconds} {__('seconds')}
                                    </div>
                                )}
                            </div>

                            {/* Visibility */}
                            <div className="space-y-1">
                                <div className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    {__('Visibility')}
                                </div>
                                <div>{renderVisibilityBadge(video.visibility)}</div>
                            </div>

                            {/* Status */}
                            <div className="space-y-1">
                                <div className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    {__('Status')}
                                </div>
                                <div>
                                    <VideoStatusBadge status={video.status} />
                                </div>
                            </div>

                            {/* Created At */}
                            <div className="space-y-1">
                                <div className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    {__('Created At')}
                                </div>
                                <div className="text-sm font-medium text-foreground">
                                    {video.created_at ? new Date(video.created_at).toLocaleString() : '—'}
                                </div>
                                {video.created_at && (
                                    <div className="font-mono text-xs text-muted-foreground">{video.created_at}</div>
                                )}
                            </div>

                            {/* Updated At */}
                            <div className="space-y-1">
                                <div className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    {__('Updated At')}
                                </div>
                                <div className="text-sm font-medium text-foreground">
                                    {video.updated_at ? new Date(video.updated_at).toLocaleString() : '—'}
                                </div>
                                {video.updated_at && (
                                    <div className="font-mono text-xs text-muted-foreground">{video.updated_at}</div>
                                )}
                            </div>

                            {/* Thumbnail Path */}
                            <div className="space-y-1">
                                <div className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    {__('Thumbnail Path')}
                                </div>
                                {video.thumbnail_path ? (
                                    <div className="flex items-center gap-1.5 font-mono text-xs text-foreground">
                                        <span className="truncate">{video.thumbnail_path}</span>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            className="h-6 w-6 shrink-0 cursor-pointer text-muted-foreground hover:text-foreground"
                                            onClick={() => copyToClipboard(video.thumbnail_path!, __('Thumbnail Path'))}
                                        >
                                            <Copy className="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="text-sm text-muted-foreground">{__('None')}</div>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Media Assets Table */}
                <Card>
                    <CardHeader className="border-b bg-muted/20 pb-4">
                        <div className="flex items-center gap-2">
                            <Layers className="h-4 w-4 text-primary" />
                            <CardTitle className="text-base font-semibold">
                                {__('Media Assets')} ({assets.length})
                            </CardTitle>
                        </div>
                        <CardDescription>
                            {__('Registered cloud storage assets and transcoded renditions')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        {assets.length === 0 ? (
                            <div className="p-8 text-center text-muted-foreground">
                                <HardDrive className="mx-auto h-8 w-8 text-muted-foreground/50" />
                                <p className="mt-2 text-sm">{__('No media assets registered for this video yet.')}</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow className="bg-muted/40 hover:bg-muted/40">
                                            <TableHead className="w-[140px]">{__('Type')}</TableHead>
                                            <TableHead className="w-[120px]">{__('Disk')}</TableHead>
                                            <TableHead>{__('Object Key')}</TableHead>
                                            <TableHead className="w-[120px]">{__('File Size')}</TableHead>
                                            <TableHead className="w-[180px]">{__('MIME Type')}</TableHead>
                                            <TableHead className="w-[180px] text-right">{__('Created')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {assets.map((asset) => (
                                            <TableRow key={asset.id} className="hover:bg-muted/20">
                                                <TableCell>
                                                    <Badge variant="secondary" className="font-mono text-xs capitalize">
                                                        {asset.type}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline" className="font-mono text-xs">
                                                        {asset.disk}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1.5 font-mono text-xs">
                                                        <span className="truncate max-w-md">{asset.object_key}</span>
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            className="h-6 w-6 shrink-0 cursor-pointer text-muted-foreground hover:text-foreground"
                                                            onClick={() => copyToClipboard(asset.object_key, __('Object Key'))}
                                                        >
                                                            <Copy className="h-3 w-3" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="font-mono text-xs">
                                                    {formatBytes(asset.size_bytes)}
                                                </TableCell>
                                                <TableCell className="font-mono text-xs text-muted-foreground">
                                                    {asset.mime_type ?? '—'}
                                                </TableCell>
                                                <TableCell className="text-right text-xs text-muted-foreground">
                                                    {asset.created_at ? new Date(asset.created_at).toLocaleString() : '—'}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Upload Sessions Table / Inspector */}
                <Card>
                    <CardHeader className="border-b bg-muted/20 pb-4">
                        <div className="flex items-center gap-2">
                            <UploadCloud className="h-4 w-4 text-primary" />
                            <CardTitle className="text-base font-semibold">
                                {__('Upload Sessions')} ({uploadSessions.length})
                            </CardTitle>
                        </div>
                        <CardDescription>
                            {__('Multipart upload sessions and cloud transfer lifecycle')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        {uploadSessions.length === 0 ? (
                            <div className="p-8 text-center text-muted-foreground">
                                <UploadCloud className="mx-auto h-8 w-8 text-muted-foreground/50" />
                                <p className="mt-2 text-sm">{__('No upload sessions recorded for this video.')}</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow className="bg-muted/40 hover:bg-muted/40">
                                            <TableHead>{__('Upload ID')}</TableHead>
                                            <TableHead>{__('Disk & Key')}</TableHead>
                                            <TableHead className="w-[110px] text-center">{__('Total Parts')}</TableHead>
                                            <TableHead className="w-[110px]">{__('Part Size')}</TableHead>
                                            <TableHead className="w-[120px]">{__('Status')}</TableHead>
                                            <TableHead className="w-[170px]">{__('Expires At')}</TableHead>
                                            <TableHead className="w-[170px] text-right">{__('Created')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {uploadSessions.map((session) => (
                                            <TableRow key={session.id} className="hover:bg-muted/20">
                                                <TableCell>
                                                    <div className="flex items-center gap-1.5 font-mono text-xs">
                                                        <span className="truncate max-w-xs">{session.upload_id}</span>
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            className="h-6 w-6 shrink-0 cursor-pointer text-muted-foreground hover:text-foreground"
                                                            onClick={() => copyToClipboard(session.upload_id, __('Upload ID'))}
                                                        >
                                                            <Copy className="h-3 w-3" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="font-mono text-xs text-muted-foreground max-w-xs truncate">
                                                        <span className="font-medium text-foreground">{session.disk}:</span>{' '}
                                                        {session.object_key}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-center font-mono text-xs">
                                                    {session.total_parts}
                                                </TableCell>
                                                <TableCell className="font-mono text-xs">
                                                    {formatBytes(session.part_size)}
                                                </TableCell>
                                                <TableCell>{renderSessionStatusBadge(session.status)}</TableCell>
                                                <TableCell className="text-xs text-muted-foreground">
                                                    {session.expires_at ? new Date(session.expires_at).toLocaleString() : '—'}
                                                </TableCell>
                                                <TableCell className="text-right text-xs text-muted-foreground">
                                                    {session.created_at ? new Date(session.created_at).toLocaleString() : '—'}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Raw Metadata JSON Card */}
                <Card>
                    <CardHeader className="border-b bg-muted/20 pb-4">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div className="space-y-1">
                                <div className="flex items-center gap-2">
                                    <FileCode className="h-4 w-4 text-primary" />
                                    <CardTitle className="text-base font-semibold">{__('Raw Video Metadata')}</CardTitle>
                                </div>
                                <CardDescription>
                                    {__('Probe inspection details, container streams, and codec metadata')}
                                </CardDescription>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                className="cursor-pointer gap-1.5 self-start sm:self-auto"
                                onClick={() =>
                                    copyToClipboard(JSON.stringify(video.metadata ?? {}, null, 2), __('Metadata JSON'))
                                }
                            >
                                <Copy className="h-3.5 w-3.5" />
                                {__('Copy JSON')}
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="pt-6">
                        <pre className="max-h-96 overflow-auto rounded-lg border bg-muted/40 p-4 font-mono text-xs leading-relaxed text-foreground">
                            {JSON.stringify(video.metadata ?? {}, null, 2)}
                        </pre>
                    </CardContent>
                </Card>

                {/* Delete Confirmation Dialog */}
                <DeleteConfirmationDialog
                    open={deleteDialogOpen}
                    onOpenChange={setDeleteDialogOpen}
                    title={__('Delete Video')}
                    description={__('Are you sure you want to permanently delete this video and purge all associated media assets from cloud storage across this tenant?')}
                    itemName={video.title}
                    onConfirm={handleDeleteVideo}
                    loading={isDeleting}
                />
            </div>
        </AdminLayout>
    );
}
