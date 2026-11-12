import DeleteConfirmationDialog from '@/components/delete-confirmation-dialog';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import PortalLayout from '@/layouts/portal-layout';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/portal';
import { destroy as destroyVideoRoute, show as showVideoRoute, update as updateVideoRoute, index as videosIndexRoute } from '@/routes/portal/videos';
import type { BreadcrumbItem, PaginatedData, SharedData } from '@/types';
import type { Video, VideoFilters, VideoStats, VideoVisibility } from '@/types/video';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { formatDistanceToNow } from 'date-fns';
import {
    AlertCircle,
    CheckCircle2,
    Copy,
    Edit3,
    Eye,
    EyeOff,
    FileEdit,
    Film,
    Globe,
    LayoutGrid,
    List,
    Loader2,
    Lock,
    MoreVertical,
    Plus,
    Search,
    Trash2,
    Upload,
    Video as VideoIcon,
    X,
} from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';
import UploadDialog from './partials/upload-dialog';
import VideoCard, { formatDuration } from './partials/video-card';
import VideoStatusBadge from './partials/video-status-badge';

interface VideosIndexProps {
    readonly videos: PaginatedData<Video>;
    readonly filters: VideoFilters;
    readonly stats: VideoStats;
}

export default function VideosIndex({ videos, filters, stats }: VideosIndexProps) {
    const { __ } = useLocalizer();
    const page = usePage<SharedData>();
    const currentTenant = page.props.currentTenant;
    const currentUser = page.props.auth?.user;

    // View mode: grid vs table
    const [viewMode, setViewMode] = useState<'grid' | 'table'>('grid');

    // Local filter state
    const [searchTerm, setSearchTerm] = useState(filters.search ?? '');

    // Upload dialog modal
    const [isUploadModalOpen, setIsUploadModalOpen] = useState(false);

    // Edit modal
    const [editingVideo, setEditingVideo] = useState<Video | null>(null);
    const [editTitle, setEditTitle] = useState('');
    const [editDescription, setEditDescription] = useState('');
    const [editVisibility, setEditVisibility] = useState<VideoVisibility>('private');
    const [isUpdating, setIsUpdating] = useState(false);
    const [editErrors, setEditErrors] = useState<Record<string, string>>({});

    // Delete dialog
    const [deletingVideo, setDeletingVideo] = useState<Video | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    // Permission helpers
    const isOwnerOrAdmin = currentTenant?.role === 'owner' || currentTenant?.role === 'admin';
    const isViewer = currentTenant?.role === 'viewer';
    const canCreate = !isViewer;

    const canEditVideo = (video: Video): boolean => {
        if (isOwnerOrAdmin) return true;
        if (currentTenant?.role === 'member' && video.user_id !== null && currentUser?.id !== undefined) {
            return String(video.user_id) === String(currentUser.id);
        }
        return false;
    };

    const canDeleteVideo = (): boolean => {
        return isOwnerOrAdmin;
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: __('Dashboard'), href: dashboard().url },
        { title: __('Videos'), href: videosIndexRoute().url },
    ];

    const applyFilter = (key: keyof VideoFilters, value: string | null) => {
        const nextFilters: Record<string, string> = {};

        const search = key === 'search' ? value : filters.search;
        const status = key === 'status' ? value : filters.status;
        const visibility = key === 'visibility' ? value : filters.visibility;

        if (search && search.trim() !== '') nextFilters.search = search.trim();
        if (status && status !== 'all' && status.trim() !== '') nextFilters.status = status;
        if (visibility && visibility !== 'all' && visibility.trim() !== '') nextFilters.visibility = visibility;

        router.get(videosIndexRoute().url, nextFilters, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilter('search', searchTerm);
    };

    const clearFilters = () => {
        setSearchTerm('');
        router.get(videosIndexRoute().url, {}, { preserveState: true, preserveScroll: true, replace: true });
    };

    const hasActiveFilters = Boolean(
        filters.search || (filters.status && filters.status !== 'all') || (filters.visibility && filters.visibility !== 'all'),
    );

    // Open Edit Dialog
    const handleOpenEdit = (video: Video) => {
        setEditingVideo(video);
        setEditTitle(video.title);
        setEditDescription(video.description ?? '');
        setEditVisibility(video.visibility);
        setEditErrors({});
    };

    // Update Handler
    const handleUpdateVideo = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingVideo) return;

        if (!editTitle.trim()) {
            setEditErrors({ title: __('Title is required.') });
            return;
        }

        setIsUpdating(true);
        setEditErrors({});

        router.put(
            updateVideoRoute(editingVideo.id).url,
            {
                title: editTitle,
                description: editDescription || null,
                visibility: editVisibility,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setEditingVideo(null);
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

    // Delete Handler
    const handleDeleteVideo = () => {
        if (!deletingVideo) return;

        setIsDeleting(true);
        router.delete(destroyVideoRoute(deletingVideo.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                setDeletingVideo(null);
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

    const handleCopyId = (id: string, e: React.MouseEvent) => {
        e.stopPropagation();
        navigator.clipboard.writeText(id);
        toast.success(__('Video ID copied to clipboard'));
    };

    return (
        <PortalLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Video Library')} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header Section */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">{__('Video Library')}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">{__('Manage, transcode, and organize your organization videos.')}</p>
                    </div>

                    {canCreate && (
                        <Button onClick={() => setIsUploadModalOpen(true)} className="inline-flex cursor-pointer items-center gap-2 shadow-sm">
                            <Plus className="size-4" />
                            <span>{__('Upload Video')}</span>
                        </Button>
                    )}
                </div>

                {/* Stats Summary Section */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    <Card className="border-border/70 p-4 shadow-none">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">{__('Total Videos')}</span>
                            <Film className="size-4 text-muted-foreground" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-foreground">{stats.total}</p>
                    </Card>

                    <Card className="border-border/70 p-4 shadow-none">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">{__('Ready')}</span>
                            <CheckCircle2 className="size-4 text-emerald-500" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{stats.ready}</p>
                    </Card>

                    <Card className="border-border/70 p-4 shadow-none">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">{__('Processing')}</span>
                            <Loader2 className="size-4 animate-spin text-cyan-500" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-cyan-600 dark:text-cyan-400">{stats.processing}</p>
                    </Card>

                    <Card className="border-border/70 p-4 shadow-none">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">{__('Drafts')}</span>
                            <FileEdit className="size-4 text-muted-foreground" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-muted-foreground">{stats.draft}</p>
                    </Card>

                    <Card className="border-border/70 p-4 shadow-none">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">{__('Failed')}</span>
                            <AlertCircle className="size-4 text-rose-500" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-rose-600 dark:text-rose-400">{stats.failed}</p>
                    </Card>
                </div>

                {/* Filter and View Controls Bar */}
                <div className="flex flex-col gap-3 rounded-lg border border-border/70 bg-card p-3 shadow-2xs sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                        {/* Search Input */}
                        <form onSubmit={handleSearchSubmit} className="relative min-w-[220px] flex-1 sm:max-w-xs">
                            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="search"
                                placeholder={__('Search videos...')}
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="h-9 pr-8 pl-8"
                            />
                            {searchTerm && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSearchTerm('');
                                        applyFilter('search', null);
                                    }}
                                    className="absolute top-1/2 right-2.5 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                >
                                    <X className="size-3.5" />
                                    <span className="sr-only">{__('Clear')}</span>
                                </button>
                            )}
                        </form>

                        {/* Status Filter */}
                        <div className="w-full sm:w-[150px]">
                            <Select value={filters.status ?? 'all'} onValueChange={(val) => applyFilter('status', val)}>
                                <SelectTrigger className="h-9">
                                    <SelectValue placeholder={__('All Statuses')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{__('All Statuses')}</SelectItem>
                                    <SelectItem value="draft">{__('Draft')}</SelectItem>
                                    <SelectItem value="uploading">{__('Uploading')}</SelectItem>
                                    <SelectItem value="uploaded">{__('Uploaded')}</SelectItem>
                                    <SelectItem value="queued">{__('Queued')}</SelectItem>
                                    <SelectItem value="probing">{__('Probing')}</SelectItem>
                                    <SelectItem value="transcoding">{__('Transcoding')}</SelectItem>
                                    <SelectItem value="packaging">{__('Packaging')}</SelectItem>
                                    <SelectItem value="ready">{__('Ready')}</SelectItem>
                                    <SelectItem value="failed">{__('Failed')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Visibility Filter */}
                        <div className="w-full sm:w-[150px]">
                            <Select value={filters.visibility ?? 'all'} onValueChange={(val) => applyFilter('visibility', val)}>
                                <SelectTrigger className="h-9">
                                    <SelectValue placeholder={__('All Visibility')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{__('All Visibility')}</SelectItem>
                                    <SelectItem value="public">{__('Public')}</SelectItem>
                                    <SelectItem value="unlisted">{__('Unlisted')}</SelectItem>
                                    <SelectItem value="private">{__('Private')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {hasActiveFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={clearFilters}
                                className="h-9 text-xs text-muted-foreground hover:text-foreground"
                            >
                                <X className="mr-1 size-3.5" />
                                {__('Clear Filters')}
                            </Button>
                        )}
                    </div>

                    {/* View Mode Toggle */}
                    <div className="flex items-center gap-1 border-border sm:border-l sm:pl-3">
                        <Button
                            variant={viewMode === 'grid' ? 'secondary' : 'ghost'}
                            size="icon"
                            className="size-8 cursor-pointer"
                            onClick={() => setViewMode('grid')}
                            title={__('Grid View')}
                        >
                            <LayoutGrid className="size-4" />
                            <span className="sr-only">{__('Grid View')}</span>
                        </Button>
                        <Button
                            variant={viewMode === 'table' ? 'secondary' : 'ghost'}
                            size="icon"
                            className="size-8 cursor-pointer"
                            onClick={() => setViewMode('table')}
                            title={__('Table View')}
                        >
                            <List className="size-4" />
                            <span className="sr-only">{__('Table View')}</span>
                        </Button>
                    </div>
                </div>

                {/* Main Content Area */}
                {videos.data.length === 0 ? (
                    /* Empty State */
                    <Card className="flex flex-col items-center justify-center border-dashed p-12 text-center">
                        <div className="flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <VideoIcon className="size-7" />
                        </div>
                        <h3 className="mt-4 text-lg font-semibold text-foreground">
                            {hasActiveFilters ? __('No videos found') : __('No videos yet')}
                        </h3>
                        <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                            {hasActiveFilters
                                ? __('No videos match your active filter criteria. Try clearing some filters or searching for something else.')
                                : __('Get started by uploading your first video to create streamable renditions and manage assets.')}
                        </p>
                        <div className="mt-6 flex items-center gap-3">
                            {hasActiveFilters ? (
                                <Button variant="outline" onClick={clearFilters} className="cursor-pointer">
                                    {__('Clear Filters')}
                                </Button>
                            ) : canCreate ? (
                                <Button onClick={() => setIsUploadModalOpen(true)} className="inline-flex cursor-pointer items-center gap-2">
                                    <Upload className="size-4" />
                                    <span>{__('Upload Video')}</span>
                                </Button>
                            ) : null}
                        </div>
                    </Card>
                ) : viewMode === 'grid' ? (
                    /* Grid View Mode */
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {videos.data.map((video) => (
                            <VideoCard
                                key={video.id}
                                video={video}
                                onEdit={handleOpenEdit}
                                onDelete={setDeletingVideo}
                                canEdit={canEditVideo(video)}
                                canDelete={canDeleteVideo()}
                            />
                        ))}
                    </div>
                ) : (
                    /* Table View Mode */
                    <Card className="overflow-hidden border border-border/70 p-0 shadow-2xs">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[40%]">{__('Video')}</TableHead>
                                    <TableHead>{__('Status')}</TableHead>
                                    <TableHead>{__('Visibility')}</TableHead>
                                    <TableHead>{__('Duration')}</TableHead>
                                    <TableHead>{__('Created')}</TableHead>
                                    <TableHead className="text-right">{__('Actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {videos.data.map((video) => {
                                    const duration = formatDuration(video.duration_seconds);
                                    const createdAgo = video.created_at ? formatDistanceToNow(new Date(video.created_at), { addSuffix: true }) : '—';

                                    return (
                                        <TableRow key={video.id} className="hover:bg-muted/40">
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <div className="relative aspect-video w-20 shrink-0 overflow-hidden rounded border border-border/50 bg-neutral-900">
                                                        {video.thumbnail_path ? (
                                                            <img src={video.thumbnail_path} alt={video.title} className="size-full object-cover" />
                                                        ) : (
                                                            <div className="flex size-full items-center justify-center bg-neutral-800 text-neutral-400">
                                                                <VideoIcon className="size-4" />
                                                            </div>
                                                        )}
                                                    </div>
                                                    <div className="min-w-0">
                                                        <Link
                                                            href={showVideoRoute(video.id).url}
                                                            className="block truncate font-medium text-foreground hover:text-primary hover:underline"
                                                            title={video.title}
                                                        >
                                                            {video.title}
                                                        </Link>
                                                        {video.description && (
                                                            <p className="line-clamp-1 text-xs text-muted-foreground">{video.description}</p>
                                                        )}
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <VideoStatusBadge status={video.status} />
                                            </TableCell>
                                            <TableCell>
                                                {video.visibility === 'public' ? (
                                                    <span className="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                                        <Globe className="size-3" />
                                                        {__('Public')}
                                                    </span>
                                                ) : video.visibility === 'unlisted' ? (
                                                    <span className="inline-flex items-center gap-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                                                        <EyeOff className="size-3" />
                                                        {__('Unlisted')}
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground">
                                                        <Lock className="size-3" />
                                                        {__('Private')}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">{duration || '—'}</TableCell>
                                            <TableCell className="text-xs text-muted-foreground" title={video.created_at}>
                                                {createdAgo}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8 text-muted-foreground hover:text-foreground"
                                                        >
                                                            <MoreVertical className="size-4" />
                                                            <span className="sr-only">{__('Actions')}</span>
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end" className="w-44">
                                                        <DropdownMenuItem asChild>
                                                            <Link
                                                                href={showVideoRoute(video.id).url}
                                                                className="flex cursor-pointer items-center gap-2"
                                                            >
                                                                <Eye className="size-4 text-muted-foreground" />
                                                                <span>{__('View Details')}</span>
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        {canEditVideo(video) && (
                                                            <DropdownMenuItem
                                                                onClick={() => handleOpenEdit(video)}
                                                                className="flex cursor-pointer items-center gap-2"
                                                            >
                                                                <Edit3 className="size-4 text-muted-foreground" />
                                                                <span>{__('Edit Details')}</span>
                                                            </DropdownMenuItem>
                                                        )}
                                                        <DropdownMenuItem
                                                            onClick={(e) => handleCopyId(video.id, e)}
                                                            className="flex cursor-pointer items-center gap-2"
                                                        >
                                                            <Copy className="size-4 text-muted-foreground" />
                                                            <span>{__('Copy Video ID')}</span>
                                                        </DropdownMenuItem>
                                                        {canDeleteVideo() && (
                                                            <>
                                                                <DropdownMenuSeparator />
                                                                <DropdownMenuItem
                                                                    onClick={() => setDeletingVideo(video)}
                                                                    className="flex cursor-pointer items-center gap-2 text-destructive focus:text-destructive"
                                                                >
                                                                    <Trash2 className="size-4" />
                                                                    <span>{__('Delete Video')}</span>
                                                                </DropdownMenuItem>
                                                            </>
                                                        )}
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </Card>
                )}

                {/* Pagination */}
                {videos.links && (
                    <div className="mt-2">
                        <Pagination data={videos} />
                    </div>
                )}
            </div>

            {/* Direct-to-MinIO Chunked Upload Dialog */}
            <UploadDialog open={isUploadModalOpen} onOpenChange={setIsUploadModalOpen} />

            {/* Edit Video Metadata Modal */}
            <Dialog open={Boolean(editingVideo)} onOpenChange={(open) => !open && setEditingVideo(null)}>
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={handleUpdateVideo}>
                        <DialogHeader>
                            <DialogTitle>{__('Edit Video')}</DialogTitle>
                            <DialogDescription>{__('Update title, description, and visibility settings.')}</DialogDescription>
                        </DialogHeader>

                        <div className="mt-4 flex flex-col gap-4">
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="edit-video-title">
                                    {__('Title')} <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="edit-video-title"
                                    value={editTitle}
                                    onChange={(e) => setEditTitle(e.target.value)}
                                    className={cn(editErrors.title && 'border-destructive focus-visible:ring-destructive')}
                                />
                                {editErrors.title && <p className="text-xs text-destructive">{editErrors.title}</p>}
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="edit-video-description">{__('Description')}</Label>
                                <Textarea
                                    id="edit-video-description"
                                    value={editDescription}
                                    onChange={(e) => setEditDescription(e.target.value)}
                                    rows={3}
                                />
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="edit-video-visibility">{__('Visibility')}</Label>
                                <Select value={editVisibility} onValueChange={(val) => setEditVisibility(val as VideoVisibility)}>
                                    <SelectTrigger id="edit-video-visibility">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="private">{__('Private')}</SelectItem>
                                        <SelectItem value="unlisted">{__('Unlisted')}</SelectItem>
                                        <SelectItem value="public">{__('Public')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <DialogFooter className="mt-6">
                            <Button type="button" variant="outline" onClick={() => setEditingVideo(null)} disabled={isUpdating}>
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
                open={Boolean(deletingVideo)}
                onOpenChange={(open) => !open && setDeletingVideo(null)}
                title={__('Delete Video')}
                description={__('Are you sure you want to permanently delete this video and all associated media files?')}
                itemName={deletingVideo?.title}
                confirmText={__('Delete Video')}
                cancelText={__('Cancel')}
                loading={isDeleting}
                onConfirm={handleDeleteVideo}
            />
        </PortalLayout>
    );
}
