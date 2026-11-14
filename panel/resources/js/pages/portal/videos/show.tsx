import DeleteConfirmationDialog from '@/components/delete-confirmation-dialog';
import { VideoPlayer } from '@/components/player/video-player';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import PortalLayout from '@/layouts/portal-layout';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/portal';
import { destroy as destroyVideoRoute, show as showVideoRoute, update as updateVideoRoute, index as videosIndexRoute } from '@/routes/portal/videos';
import type { BreadcrumbItem, SharedData } from '@/types';
import type { Video, VideoAsset, VideoVisibility } from '@/types/video';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { format, formatDistanceToNow } from 'date-fns';
import {
    AlertTriangle,
    ArrowLeft,
    Check,
    CheckCircle2,
    Clock,
    Copy,
    Edit3,
    EyeOff,
    FileText,
    Globe,
    HardDrive,
    Layers,
    Loader2,
    Lock,
    Package,
    Play,
    Search,
    Trash2,
    User as UserIcon,
    Video as VideoIcon,
} from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';
import { formatDuration } from './partials/video-card';
import VideoStatusBadge from './partials/video-status-badge';

interface VideoShowProps {
    readonly video: Video;
    readonly playback_url?: string | null;
}

export function formatBytes(bytes: number | null | undefined): string {
    if (bytes === null || bytes === undefined || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    const val = (bytes / Math.pow(k, i)).toFixed(2);
    return `${val} ${sizes[i]}`;
}

export default function VideoShow({ video, playback_url }: VideoShowProps) {
    const { __ } = useLocalizer();
    const page = usePage<SharedData>();
    const currentTenant = page.props.currentTenant;
    const currentUser = page.props.auth?.user;

    // Edit Dialog State
    const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
    const [title, setTitle] = useState(video.title);
    const [description, setDescription] = useState(video.description ?? '');
    const [visibility, setVisibility] = useState<VideoVisibility>(video.visibility);
    const [isUpdating, setIsUpdating] = useState(false);
    const [editErrors, setEditErrors] = useState<Record<string, string>>({});

    // Delete Dialog State
    const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    // Permission helpers
    const isOwnerOrAdmin = currentTenant?.role === 'owner' || currentTenant?.role === 'admin';
    const canEdit =
        isOwnerOrAdmin ||
        (currentTenant?.role === 'member' &&
            video.user_id !== null &&
            currentUser?.id !== undefined &&
            String(video.user_id) === String(currentUser.id));
    const canDelete = isOwnerOrAdmin;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: __('Dashboard'), href: dashboard().url },
        { title: __('Videos'), href: videosIndexRoute().url },
        { title: video.title, href: showVideoRoute(video.id).url },
    ];

    const copyToClipboard = (text: string, label: string) => {
        navigator.clipboard.writeText(text);
        toast.success(__(':label copied to clipboard', { label }));
    };

    const handleUpdate = (e: React.FormEvent) => {
        e.preventDefault();
        if (!title.trim()) {
            setEditErrors({ title: __('Title is required.') });
            return;
        }

        setIsUpdating(true);
        setEditErrors({});

        router.put(
            updateVideoRoute(video.id).url,
            {
                title,
                description: description || null,
                visibility,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsEditDialogOpen(false);
                    toast.success(__('Video updated successfully.'));
                },
                onError: (errs) => {
                    setEditErrors(errs);
                },
                onFinish: () => {
                    setIsUpdating(false);
                },
            },
        );
    };

    const handleDelete = () => {
        setIsDeleting(true);
        router.delete(destroyVideoRoute(video.id).url, {
            onSuccess: () => {
                toast.success(__('Video deleted successfully.'));
            },
            onError: () => {
                toast.error(__('Failed to delete video.'));
            },
            onFinish: () => {
                setIsDeleting(false);
            },
        });
    };

    // Helper for Status Banner
    const renderStatusBanner = () => {
        switch (video.status) {
            case 'draft':
                return (
                    <div className="flex flex-col gap-3 rounded-lg border border-border bg-muted/40 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                <FileText className="size-5" />
                            </div>
                            <div>
                                <h4 className="text-sm font-semibold text-foreground">{__('Draft Video')}</h4>
                                <p className="text-xs text-muted-foreground">
                                    {__('Media has not been uploaded yet. Upload your source file to begin transcoding.')}
                                </p>
                            </div>
                        </div>
                    </div>
                );
            case 'uploading':
                return (
                    <div className="flex items-center gap-3 rounded-lg border border-blue-500/30 bg-blue-500/10 p-4 text-blue-900 dark:text-blue-100">
                        <Loader2 className="size-5 shrink-0 animate-spin text-blue-600 dark:text-blue-400" />
                        <div>
                            <h4 className="text-sm font-semibold">{__('Uploading Media')}</h4>
                            <p className="text-xs text-blue-700 dark:text-blue-300">
                                {__('An upload session is currently active and receiving file chunks.')}
                            </p>
                        </div>
                    </div>
                );
            case 'uploaded':
                return (
                    <div className="flex items-center gap-3 rounded-lg border border-indigo-500/30 bg-indigo-500/10 p-4 text-indigo-900 dark:text-indigo-100">
                        <Check className="size-5 shrink-0 text-indigo-600 dark:text-indigo-400" />
                        <div>
                            <h4 className="text-sm font-semibold">{__('Upload Completed')}</h4>
                            <p className="text-xs text-indigo-700 dark:text-indigo-300">
                                {__('Source file has been received and is waiting to enter the processing queue.')}
                            </p>
                        </div>
                    </div>
                );
            case 'queued':
                return (
                    <div className="flex items-center gap-3 rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-amber-900 dark:text-amber-100">
                        <Clock className="size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                        <div>
                            <h4 className="text-sm font-semibold">{__('Queued for Processing')}</h4>
                            <p className="text-xs text-amber-700 dark:text-amber-300">
                                {__('This video is queued and waiting for an available transcoding worker.')}
                            </p>
                        </div>
                    </div>
                );
            case 'probing':
                return (
                    <div className="flex items-center gap-3 rounded-lg border border-purple-500/30 bg-purple-500/10 p-4 text-purple-900 dark:text-purple-100">
                        <Search className="size-5 shrink-0 animate-spin text-purple-600 dark:text-purple-400" />
                        <div>
                            <h4 className="text-sm font-semibold">{__('Probing Media Streams')}</h4>
                            <p className="text-xs text-purple-700 dark:text-purple-300">
                                {__('Analyzing video and audio tracks, codecs, frame rate, and dimensions.')}
                            </p>
                        </div>
                    </div>
                );
            case 'transcoding':
                return (
                    <div className="flex items-center gap-3 rounded-lg border border-cyan-500/30 bg-cyan-500/10 p-4 text-cyan-900 dark:text-cyan-100">
                        <Loader2 className="size-5 shrink-0 animate-spin text-cyan-600 dark:text-cyan-400" />
                        <div>
                            <h4 className="text-sm font-semibold">{__('Transcoding in Progress')}</h4>
                            <p className="text-xs text-cyan-700 dark:text-cyan-300">
                                {__('Generating adaptive multi-bitrate renditions for fast global delivery.')}
                            </p>
                        </div>
                    </div>
                );
            case 'packaging':
                return (
                    <div className="flex items-center gap-3 rounded-lg border border-orange-500/30 bg-orange-500/10 p-4 text-orange-900 dark:text-orange-100">
                        <Package className="size-5 shrink-0 animate-spin text-orange-600 dark:text-orange-400" />
                        <div>
                            <h4 className="text-sm font-semibold">{__('Packaging HLS Playlist')}</h4>
                            <p className="text-xs text-orange-700 dark:text-orange-300">
                                {__('Creating HLS master playlist manifests and segmented media files.')}
                            </p>
                        </div>
                    </div>
                );
            case 'ready':
                return (
                    <div className="flex items-center gap-3 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-900 dark:text-emerald-100">
                        <CheckCircle2 className="size-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        <div>
                            <h4 className="text-sm font-semibold">{__('Video Ready for Streaming')}</h4>
                            <p className="text-xs text-emerald-700 dark:text-emerald-300">
                                {__('Video has been successfully processed and all renditions are available for playback.')}
                            </p>
                        </div>
                    </div>
                );
            case 'failed':
                return (
                    <div className="flex items-center gap-3 rounded-lg border border-rose-500/30 bg-rose-500/10 p-4 text-rose-900 dark:text-rose-100">
                        <AlertTriangle className="size-5 shrink-0 text-rose-600 dark:text-rose-400" />
                        <div>
                            <h4 className="text-sm font-semibold">{__('Processing Failed')}</h4>
                            <p className="text-xs text-rose-700 dark:text-rose-300">
                                {__('An unexpected error occurred while processing this video. Check registered assets or upload again.')}
                            </p>
                        </div>
                    </div>
                );
            default:
                return null;
        }
    };

    const renderAssetTypeBadge = (type: string) => {
        switch (type.toLowerCase()) {
            case 'original':
                return (
                    <Badge variant="secondary" className="font-mono text-xs">
                        {__('Original')}
                    </Badge>
                );
            case 'master':
                return (
                    <Badge variant="outline" className="border-indigo-500/30 bg-indigo-500/10 font-mono text-xs text-indigo-700 dark:text-indigo-300">
                        {__('Master')}
                    </Badge>
                );
            case 'hls_playlist':
                return (
                    <Badge variant="outline" className="border-cyan-500/30 bg-cyan-500/10 font-mono text-xs text-cyan-700 dark:text-cyan-300">
                        {__('HLS Playlist')}
                    </Badge>
                );
            case 'hls_segment':
                return (
                    <Badge variant="outline" className="border-amber-500/30 bg-amber-500/10 font-mono text-xs text-amber-700 dark:text-amber-300">
                        {__('HLS Segment')}
                    </Badge>
                );
            case 'thumbnail':
                return (
                    <Badge
                        variant="outline"
                        className="border-emerald-500/30 bg-emerald-500/10 font-mono text-xs text-emerald-700 dark:text-emerald-300"
                    >
                        {__('Thumbnail')}
                    </Badge>
                );
            default:
                return <Badge variant="outline">{type}</Badge>;
        }
    };

    const latestUploadSession =
        video.latest_upload_session ?? (video.upload_sessions && video.upload_sessions.length > 0 ? video.upload_sessions[0] : null);

    return (
        <PortalLayout breadcrumbs={breadcrumbs}>
            <Head title={video.title} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Back Link and Action Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" size="icon" asChild className="size-9 shrink-0">
                            <Link href={videosIndexRoute().url} title={__('Back to Videos')}>
                                <ArrowLeft className="size-4" />
                                <span className="sr-only">{__('Back to Videos')}</span>
                            </Link>
                        </Button>
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="truncate text-xl font-bold tracking-tight text-foreground sm:text-2xl">{video.title}</h1>
                                <VideoStatusBadge status={video.status} />
                                {video.visibility === 'public' ? (
                                    <Badge
                                        variant="outline"
                                        className="border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                    >
                                        <Globe className="mr-1 size-3" />
                                        {__('Public')}
                                    </Badge>
                                ) : video.visibility === 'unlisted' ? (
                                    <Badge variant="outline" className="border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300">
                                        <EyeOff className="mr-1 size-3" />
                                        {__('Unlisted')}
                                    </Badge>
                                ) : (
                                    <Badge variant="outline" className="text-muted-foreground">
                                        <Lock className="mr-1 size-3" />
                                        {__('Private')}
                                    </Badge>
                                )}
                            </div>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {__('ID:')} <span className="font-mono">{video.id}</span>
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => copyToClipboard(video.id, __('Video ID'))}
                            className="inline-flex cursor-pointer items-center gap-1.5"
                        >
                            <Copy className="size-3.5" />
                            <span>{__('Copy ID')}</span>
                        </Button>

                        {canEdit && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setIsEditDialogOpen(true)}
                                className="inline-flex cursor-pointer items-center gap-1.5"
                            >
                                <Edit3 className="size-3.5" />
                                <span>{__('Edit Details')}</span>
                            </Button>
                        )}

                        {canDelete && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setIsDeleteDialogOpen(true)}
                                className="inline-flex cursor-pointer items-center gap-1.5 border-destructive/30 text-destructive hover:bg-destructive/10 hover:text-destructive"
                            >
                                <Trash2 className="size-3.5" />
                                <span>{__('Delete')}</span>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Status Callout Banner */}
                {renderStatusBanner()}

                {/* Two-Column Layout */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Main Content Area (2 Cols on lg) */}
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        {/* Video Player / Thumbnail Preview Card */}
                        <Card className="overflow-hidden border-border/70 p-0 shadow-2xs">
                            <div className="relative aspect-video w-full bg-neutral-950">
                                {video.status === 'ready' && (playback_url || video.playback_url) ? (
                                    <VideoPlayer
                                        src={(playback_url || video.playback_url)!}
                                        poster={video.thumbnail_path ?? undefined}
                                        title={video.title}
                                        className="size-full"
                                    />
                                ) : video.thumbnail_path ? (
                                    <img src={video.thumbnail_path} alt={video.title} className="size-full object-contain" />
                                ) : (
                                    <div className="flex size-full flex-col items-center justify-center gap-3 bg-gradient-to-br from-neutral-900 to-black text-neutral-400">
                                        <div className="flex size-16 items-center justify-center rounded-full bg-neutral-800/80 ring-1 ring-white/10">
                                            {video.status === 'ready' ? (
                                                <Play className="ml-1 size-8 text-neutral-200" />
                                            ) : (
                                                <VideoIcon className="size-8 text-neutral-400" />
                                            )}
                                        </div>
                                        <p className="text-xs text-neutral-400">
                                            {video.status === 'ready' ? __('Playback ready') : __('Thumbnail will appear once processed')}
                                        </p>
                                    </div>
                                )}

                                {!(video.status === 'ready' && (playback_url || video.playback_url)) && video.duration_seconds && (
                                    <div className="absolute right-3 bottom-3 rounded bg-black/85 px-2 py-0.5 font-mono text-xs font-semibold text-white backdrop-blur-xs">
                                        {formatDuration(video.duration_seconds)}
                                    </div>
                                )}
                            </div>
                        </Card>

                        {/* Metadata Details Card */}
                        <Card className="border-border/70 shadow-2xs">
                            <CardHeader>
                                <CardTitle className="text-base font-semibold">{__('Metadata Details')}</CardTitle>
                                <CardDescription>{__('Primary description and attributes for this video record.')}</CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <div>
                                    <h4 className="text-xs font-medium text-muted-foreground uppercase">{__('Description')}</h4>
                                    <p className="mt-1 text-sm whitespace-pre-wrap text-foreground">
                                        {video.description || (
                                            <span className="text-muted-foreground/60 italic">{__('No description provided.')}</span>
                                        )}
                                    </p>
                                </div>

                                {video.metadata && Object.keys(video.metadata).length > 0 && (
                                    <div className="border-t border-border/50 pt-4">
                                        <h4 className="mb-2 text-xs font-medium text-muted-foreground uppercase">
                                            {__('Technical Stream Properties')}
                                        </h4>
                                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                            {Object.entries(video.metadata).map(([key, val]) => (
                                                <div key={key} className="rounded-md border border-border/50 bg-muted/30 p-2">
                                                    <span className="block font-mono text-[11px] text-muted-foreground">{key}</span>
                                                    <span className="font-mono text-xs font-medium text-foreground">
                                                        {typeof val === 'object' ? JSON.stringify(val) : String(val)}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Registered Assets Card */}
                        <Card className="border-border/70 shadow-2xs">
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle className="text-base font-semibold">{__('Registered Media Assets')}</CardTitle>
                                    <CardDescription>{__('Original files, transcoded masters, and HLS streaming playlists.')}</CardDescription>
                                </div>
                                <Badge variant="secondary">
                                    {video.assets?.length ?? 0} {__('Assets')}
                                </Badge>
                            </CardHeader>
                            <CardContent className="p-0">
                                {!video.assets || video.assets.length === 0 ? (
                                    <div className="flex flex-col items-center justify-center p-8 text-center text-muted-foreground">
                                        <Layers className="size-8 text-muted-foreground/40" />
                                        <p className="mt-2 text-sm font-medium">{__('No media assets registered')}</p>
                                        <p className="text-xs text-muted-foreground/70">
                                            {__('Assets will be generated automatically once media is uploaded and transcoded.')}
                                        </p>
                                    </div>
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>{__('Type')}</TableHead>
                                                <TableHead>{__('Disk & Object Key')}</TableHead>
                                                <TableHead>{__('Size')}</TableHead>
                                                <TableHead>{__('MIME Type')}</TableHead>
                                                <TableHead className="text-right">{__('Created')}</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {video.assets.map((asset: VideoAsset) => (
                                                <TableRow key={asset.id}>
                                                    <TableCell>{renderAssetTypeBadge(asset.type)}</TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center gap-1.5">
                                                            <HardDrive className="size-3.5 shrink-0 text-muted-foreground" />
                                                            <span className="font-mono text-xs font-semibold text-muted-foreground">
                                                                [{asset.disk}]
                                                            </span>
                                                            <span
                                                                className="max-w-xs truncate font-mono text-xs text-foreground"
                                                                title={asset.object_key}
                                                            >
                                                                {asset.object_key}
                                                            </span>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="font-mono text-xs">{formatBytes(asset.size_bytes)}</TableCell>
                                                    <TableCell className="font-mono text-xs text-muted-foreground">
                                                        {asset.mime_type || '—'}
                                                    </TableCell>
                                                    <TableCell className="text-right text-xs text-muted-foreground">
                                                        {asset.created_at ? format(new Date(asset.created_at), 'yyyy-MM-dd HH:mm') : '—'}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Sidebar / Quick Info Column (1 Col on lg) */}
                    <div className="flex flex-col gap-6">
                        {/* Summary Attributes Card */}
                        <Card className="border-border/70 shadow-2xs">
                            <CardHeader>
                                <CardTitle className="text-base font-semibold">{__('Properties')}</CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-3 text-sm">
                                <div className="flex items-center justify-between border-b border-border/50 pb-2">
                                    <span className="text-muted-foreground">{__('Status')}</span>
                                    <VideoStatusBadge status={video.status} />
                                </div>

                                <div className="flex items-center justify-between border-b border-border/50 pb-2">
                                    <span className="text-muted-foreground">{__('Visibility')}</span>
                                    <span className="font-medium text-foreground">
                                        {video.visibility === 'public'
                                            ? __('Public')
                                            : video.visibility === 'unlisted'
                                              ? __('Unlisted')
                                              : __('Private')}
                                    </span>
                                </div>

                                <div className="flex items-center justify-between border-b border-border/50 pb-2">
                                    <span className="text-muted-foreground">{__('Duration')}</span>
                                    <span className="font-mono font-medium text-foreground">{formatDuration(video.duration_seconds) || '—'}</span>
                                </div>

                                <div className="flex items-center justify-between border-b border-border/50 pb-2">
                                    <span className="text-muted-foreground">{__('Created')}</span>
                                    <span className="text-foreground" title={video.created_at}>
                                        {video.created_at ? format(new Date(video.created_at), 'MMM d, yyyy HH:mm') : '—'}
                                    </span>
                                </div>

                                <div className="flex items-center justify-between border-b border-border/50 pb-2">
                                    <span className="text-muted-foreground">{__('Last Updated')}</span>
                                    <span className="text-foreground" title={video.updated_at}>
                                        {video.updated_at ? formatDistanceToNow(new Date(video.updated_at), { addSuffix: true }) : '—'}
                                    </span>
                                </div>

                                {video.user && (
                                    <div className="flex items-center justify-between pt-1">
                                        <span className="text-muted-foreground">{__('Uploaded by')}</span>
                                        <div className="flex items-center gap-1.5 text-right">
                                            <UserIcon className="size-3.5 text-muted-foreground" />
                                            <span className="font-medium text-foreground" title={video.user.email}>
                                                {video.user.name}
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Recent Upload Session Card (if exists) */}
                        {latestUploadSession && (
                            <Card className="border-border/70 shadow-2xs">
                                <CardHeader>
                                    <CardTitle className="text-base font-semibold">{__('Upload Session')}</CardTitle>
                                    <CardDescription>{__('Details for multipart upload.')}</CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-2.5 text-xs">
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">{__('Session Status')}</span>
                                        <Badge
                                            variant={
                                                latestUploadSession.status === 'completed'
                                                    ? 'success'
                                                    : latestUploadSession.status === 'pending'
                                                      ? 'warning'
                                                      : 'secondary'
                                            }
                                        >
                                            {latestUploadSession.status === 'completed'
                                                ? __('Completed')
                                                : latestUploadSession.status === 'pending'
                                                  ? __('Pending')
                                                  : latestUploadSession.status === 'aborted'
                                                    ? __('Aborted')
                                                    : __('Expired')}
                                        </Badge>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">{__('Total Parts')}</span>
                                        <span className="font-mono font-medium">{latestUploadSession.total_parts}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">{__('Part Size')}</span>
                                        <span className="font-mono font-medium">{formatBytes(latestUploadSession.part_size)}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">{__('Storage Disk')}</span>
                                        <span className="font-mono font-medium">{latestUploadSession.disk}</span>
                                    </div>
                                    <div className="flex flex-col gap-1 border-t border-border/50 pt-2">
                                        <span className="text-muted-foreground">{__('Upload ID')}</span>
                                        <span className="truncate font-mono text-[11px] text-foreground" title={latestUploadSession.upload_id}>
                                            {latestUploadSession.upload_id}
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>

            {/* Edit Metadata Dialog */}
            <Dialog open={isEditDialogOpen} onOpenChange={setIsEditDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={handleUpdate}>
                        <DialogHeader>
                            <DialogTitle>{__('Edit Video Details')}</DialogTitle>
                            <DialogDescription>{__('Update the video title, description, or visibility level.')}</DialogDescription>
                        </DialogHeader>

                        <div className="mt-4 flex flex-col gap-4">
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="edit-title">
                                    {__('Title')} <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="edit-title"
                                    value={title}
                                    onChange={(e) => setTitle(e.target.value)}
                                    className={cn(editErrors.title && 'border-destructive focus-visible:ring-destructive')}
                                />
                                {editErrors.title && <p className="text-xs text-destructive">{editErrors.title}</p>}
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="edit-description">{__('Description')}</Label>
                                <Textarea id="edit-description" value={description} onChange={(e) => setDescription(e.target.value)} rows={4} />
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="edit-visibility">{__('Visibility')}</Label>
                                <Select value={visibility} onValueChange={(val) => setVisibility(val as VideoVisibility)}>
                                    <SelectTrigger id="edit-visibility">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="private">
                                            {__('Private')} — <span className="text-xs text-muted-foreground">{__('Only organization members')}</span>
                                        </SelectItem>
                                        <SelectItem value="unlisted">
                                            {__('Unlisted')} — <span className="text-xs text-muted-foreground">{__('Anyone with direct link')}</span>
                                        </SelectItem>
                                        <SelectItem value="public">
                                            {__('Public')} — <span className="text-xs text-muted-foreground">{__('Publicly discoverable')}</span>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <DialogFooter className="mt-6">
                            <Button type="button" variant="outline" onClick={() => setIsEditDialogOpen(false)} disabled={isUpdating}>
                                {__('Cancel')}
                            </Button>
                            <Button type="submit" disabled={isUpdating} className="cursor-pointer">
                                {isUpdating ? (
                                    <span className="flex items-center gap-2">
                                        <Loader2 className="size-4 animate-spin" />
                                        {__('Saving...')}
                                    </span>
                                ) : (
                                    __('Save Changes')
                                )}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Dialog */}
            <DeleteConfirmationDialog
                open={isDeleteDialogOpen}
                onOpenChange={setIsDeleteDialogOpen}
                title={__('Delete Video')}
                description={__('Are you sure you want to permanently delete this video and remove all associated media assets from cloud storage?')}
                itemName={video.title}
                confirmText={__('Delete Video')}
                cancelText={__('Cancel')}
                loading={isDeleting}
                onConfirm={handleDelete}
            />
        </PortalLayout>
    );
}
