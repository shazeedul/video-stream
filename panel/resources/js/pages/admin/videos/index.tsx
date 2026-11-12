import DeleteConfirmationDialog from '@/components/delete-confirmation-dialog';
import FilterPanel from '@/components/filter-panel';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import AdminLayout from '@/layouts/admin-layout';
import VideoStatusBadge from '@/pages/portal/videos/partials/video-status-badge';
import { dashboard } from '@/routes/admin';
import { destroy as videosDestroy, index as videosIndex, show as videosShow } from '@/routes/admin/videos';
import { type BreadcrumbItem, type PaginatedData, type Video } from '@/types';
import { type FilterConfig } from '@/types/filters';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Can } from '@devwizard/laravel-react-permissions';
import { Head, Link, router } from '@inertiajs/react';
import { Building2, Clock, Eye, EyeOff, Film, Globe, Lock, MoreHorizontal, Trash2, User as UserIcon } from 'lucide-react';
import React, { useState } from 'react';

interface Props {
    videos: PaginatedData<Video>;
    tenants: Array<{ id: string; name: string }>;
    filters: {
        search: string | null;
        tenant_id: string | null;
        status: string | null;
        per_page?: number;
    };
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

export default function Index({ videos, tenants, filters }: Props) {
    const { __ } = useLocalizer();

    const [videoToDelete, setVideoToDelete] = useState<Video | null>(null);
    const [deleteLoading, setDeleteLoading] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('Admin'),
            href: dashboard().url,
        },
        {
            title: __('Videos'),
            href: videosIndex().url,
        },
    ];

    const handleDelete = (video: Video) => {
        setVideoToDelete(video);
    };

    const confirmDelete = () => {
        if (!videoToDelete) return;

        setDeleteLoading(true);
        router.delete(videosDestroy({ video: videoToDelete.id }).url, {
            onSuccess: () => {
                setVideoToDelete(null);
                setDeleteLoading(false);
            },
            onError: () => {
                setDeleteLoading(false);
            },
            preserveScroll: true,
        });
    };

    const filterConfig: FilterConfig = {
        fields: [
            {
                key: 'search',
                type: 'search',
                label: __('Search Videos'),
                placeholder: __('Search by title, description...'),
                defaultValue: '',
            },
            {
                key: 'tenant_id',
                type: 'select',
                label: __('Tenant'),
                placeholder: __('Filter by tenant'),
                defaultValue: 'all',
                options: [
                    { value: 'all', label: __('All Tenants') },
                    ...tenants.map((tenant) => ({
                        value: tenant.id,
                        label: tenant.name,
                    })),
                ],
            },
            {
                key: 'status',
                type: 'select',
                label: __('Status'),
                placeholder: __('Filter by status'),
                defaultValue: 'all',
                options: [
                    { value: 'all', label: __('All Statuses') },
                    { value: 'draft', label: __('Draft') },
                    { value: 'uploading', label: __('Uploading') },
                    { value: 'uploaded', label: __('Uploaded') },
                    { value: 'queued', label: __('Queued') },
                    { value: 'probing', label: __('Probing') },
                    { value: 'transcoding', label: __('Transcoding') },
                    { value: 'packaging', label: __('Packaging') },
                    { value: 'ready', label: __('Ready') },
                    { value: 'failed', label: __('Failed') },
                ],
            },
            {
                key: 'per_page',
                type: 'select',
                label: __('Per Page'),
                placeholder: __('Items per page'),
                defaultValue: 15,
                options: [
                    { value: 10, label: __('10 per page') },
                    { value: 15, label: __('15 per page') },
                    { value: 25, label: __('25 per page') },
                    { value: 50, label: __('50 per page') },
                ],
            },
        ],
        defaultFilters: {
            search: '',
            tenant_id: 'all',
            status: 'all',
            per_page: 15,
        },
        baseUrl: videosIndex().url,
        preserveScroll: true,
        preserveState: true,
        only: ['videos', 'filters'],
        debounceMs: 300,
    };

    const initialFilters = {
        search: filters.search ?? '',
        tenant_id: filters.tenant_id ?? 'all',
        status: filters.status ?? 'all',
        per_page: filters.per_page ?? 15,
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

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Videos')} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                            <Film className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold">{__('Videos')}</h1>
                            <p className="text-sm text-muted-foreground">
                                {__('Platform-wide video oversight and moderation across all tenants')}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Filters */}
                <FilterPanel config={filterConfig} initialFilters={initialFilters} />

                {/* Videos Table */}
                {videos.data.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-8 text-center bg-card">
                        <Film className="mx-auto h-12 w-12 text-muted-foreground/50" />
                        <h3 className="mt-4 text-lg font-medium">{__('No videos found')}</h3>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {filters.search || (filters.tenant_id && filters.tenant_id !== 'all') || (filters.status && filters.status !== 'all')
                                ? __('Try adjusting your search criteria or filters.')
                                : __('No videos have been uploaded yet.')}
                        </p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-md border bg-card">
                        <table className="w-full">
                            <thead className="bg-muted/50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-sm font-medium">{__('Title')}</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">{__('Tenant')}</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">{__('Uploader')}</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">{__('Status')}</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">{__('Visibility')}</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">{__('Duration')}</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">{__('Created')}</th>
                                    <th className="px-4 py-3 text-right text-sm font-medium">{__('Actions')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {videos.data.map((video) => (
                                    <tr key={video.id} className="hover:bg-muted/25 transition-colors">
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-10 w-14 shrink-0 items-center justify-center overflow-hidden rounded bg-muted/60 text-muted-foreground border">
                                                    {video.thumbnail_path ? (
                                                        <img
                                                            src={`/storage/${video.thumbnail_path}`}
                                                            alt={video.title}
                                                            className="h-full w-full object-cover"
                                                        />
                                                    ) : (
                                                        <Film className="h-5 w-5" />
                                                    )}
                                                </div>
                                                <div className="min-w-0 max-w-xs">
                                                    <Link
                                                        href={videosShow({ video: video.id }).url}
                                                        className="font-medium text-foreground hover:underline truncate block"
                                                    >
                                                        {video.title}
                                                    </Link>
                                                    {video.description ? (
                                                        <p className="text-xs text-muted-foreground truncate">{video.description}</p>
                                                    ) : (
                                                        <span className="font-mono text-xs text-muted-foreground/70 truncate block">{video.id}</span>
                                                    )}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant="outline" className="font-normal inline-flex items-center gap-1">
                                                <Building2 className="h-3 w-3 text-muted-foreground" />
                                                <span>{video.tenant?.name ?? video.tenant_id}</span>
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <UserIcon className="h-4 w-4 shrink-0 text-muted-foreground" />
                                                <div className="min-w-0">
                                                    <div className="text-sm font-medium text-foreground truncate">
                                                        {video.user?.name ?? __('System')}
                                                    </div>
                                                    {video.user?.email && (
                                                        <div className="text-xs text-muted-foreground truncate">{video.user.email}</div>
                                                    )}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <VideoStatusBadge status={video.status} />
                                        </td>
                                        <td className="px-4 py-3">
                                            {renderVisibilityBadge(video.visibility)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-1 text-sm text-muted-foreground">
                                                <Clock className="h-3.5 w-3.5" />
                                                <span>{formatDuration(video.duration_seconds)}</span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-muted-foreground">
                                            {video.created_at ? new Date(video.created_at).toLocaleDateString() : '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button size="sm" variant="ghost" className="cursor-pointer">
                                                            <MoreHorizontal className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <Can permission="videos.view">
                                                            <DropdownMenuItem asChild className="cursor-pointer">
                                                                <Link href={videosShow({ video: video.id }).url}>
                                                                    <Eye className="mr-2 h-4 w-4" />
                                                                    {__('Inspect')}
                                                                </Link>
                                                            </DropdownMenuItem>
                                                        </Can>

                                                        <Can permission="videos.delete">
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                onClick={() => handleDelete(video)}
                                                                className="cursor-pointer text-destructive focus:text-destructive"
                                                            >
                                                                <Trash2 className="mr-2 h-4 w-4" />
                                                                {__('Delete')}
                                                            </DropdownMenuItem>
                                                        </Can>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Pagination */}
                <Pagination data={videos} />

                {/* Delete Confirmation Dialog */}
                <DeleteConfirmationDialog
                    open={!!videoToDelete}
                    onOpenChange={() => setVideoToDelete(null)}
                    title={__('Delete Video')}
                    description={__('Are you sure you want to permanently delete this video and purge all associated media assets from cloud storage across this tenant?')}
                    itemName={videoToDelete?.title}
                    onConfirm={confirmDelete}
                    loading={deleteLoading}
                />
            </div>
        </AdminLayout>
    );
}
