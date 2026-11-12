import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { show as showVideoRoute } from '@/routes/portal/videos';
import type { Video } from '@/types/video';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Link } from '@inertiajs/react';
import { formatDistanceToNow } from 'date-fns';
import { Copy, Edit3, Eye, EyeOff, Globe, Lock, MoreVertical, Trash2, Video as VideoIcon } from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';
import VideoStatusBadge from './video-status-badge';

interface VideoCardProps {
    readonly video: Video;
    readonly onEdit?: (video: Video) => void;
    readonly onDelete?: (video: Video) => void;
    readonly canEdit?: boolean;
    readonly canDelete?: boolean;
}

export function formatDuration(seconds: number | null | undefined): string {
    if (seconds === null || seconds === undefined || seconds <= 0) {
        return '';
    }
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.floor(seconds % 60);

    if (h > 0) {
        return `${h}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
    }
    return `${m}:${s.toString().padStart(2, '0')}`;
}

export function VideoCard({ video, onEdit, onDelete, canEdit = true, canDelete = true }: VideoCardProps) {
    const { __ } = useLocalizer();
    const [imageError, setImageError] = useState(false);

    const durationText = formatDuration(video.duration_seconds);
    const formattedDate = video.created_at ? formatDistanceToNow(new Date(video.created_at), { addSuffix: true }) : '';

    const handleCopyId = (e: React.MouseEvent) => {
        e.stopPropagation();
        navigator.clipboard.writeText(video.id);
        toast.success(__('Video ID copied to clipboard'));
    };

    const renderVisibility = () => {
        switch (video.visibility) {
            case 'public':
                return (
                    <span className="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-600 dark:text-emerald-400">
                        <Globe className="size-3" />
                        {__('Public')}
                    </span>
                );
            case 'unlisted':
                return (
                    <span className="inline-flex items-center gap-1 text-[11px] font-medium text-amber-600 dark:text-amber-400">
                        <EyeOff className="size-3" />
                        {__('Unlisted')}
                    </span>
                );
            case 'private':
            default:
                return (
                    <span className="inline-flex items-center gap-1 text-[11px] font-medium text-muted-foreground">
                        <Lock className="size-3" />
                        {__('Private')}
                    </span>
                );
        }
    };

    return (
        <Card className="group overflow-hidden border border-border/70 bg-card p-0 transition-all duration-200 hover:border-primary/40 hover:shadow-md">
            {/* Thumbnail Header */}
            <div className="relative aspect-video w-full overflow-hidden bg-neutral-900">
                {video.thumbnail_path && !imageError ? (
                    <img
                        src={video.thumbnail_path}
                        alt={video.title}
                        className="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                        onError={() => setImageError(true)}
                    />
                ) : (
                    <div className="flex size-full flex-col items-center justify-center bg-gradient-to-br from-neutral-800 to-neutral-950 text-neutral-400">
                        <div className="flex size-12 items-center justify-center rounded-full bg-neutral-800/80 ring-1 ring-white/10 transition-transform duration-300 group-hover:scale-110">
                            <VideoIcon className="size-6 text-neutral-300" />
                        </div>
                    </div>
                )}

                {/* Status Overlay Badge */}
                <div className="absolute top-2 left-2 z-10">
                    <VideoStatusBadge status={video.status} className="bg-background/90 backdrop-blur-xs" />
                </div>

                {/* Duration Overlay */}
                {durationText && (
                    <div className="absolute right-2 bottom-2 z-10 rounded bg-black/85 px-1.5 py-0.5 font-mono text-[11px] font-medium text-white backdrop-blur-xs">
                        {durationText}
                    </div>
                )}

                {/* Clickable Overlay Link */}
                <Link href={showVideoRoute(video.id).url} className="absolute inset-0 z-0 focus:outline-none" aria-label={video.title} />
            </div>

            {/* Card Content */}
            <CardContent className="flex flex-col gap-2 p-4">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0 flex-1">
                        <Link
                            href={showVideoRoute(video.id).url}
                            className="block truncate font-semibold text-foreground transition-colors hover:text-primary focus:outline-none"
                            title={video.title}
                        >
                            {video.title}
                        </Link>
                        {video.description ? (
                            <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">{video.description}</p>
                        ) : (
                            <p className="mt-1 text-xs text-muted-foreground/60 italic">{__('No description')}</p>
                        )}
                    </div>

                    {/* Actions Menu */}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" className="size-8 shrink-0 text-muted-foreground hover:text-foreground">
                                <MoreVertical className="size-4" />
                                <span className="sr-only">{__('Actions')}</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-44">
                            <DropdownMenuItem asChild>
                                <Link href={showVideoRoute(video.id).url} className="flex w-full cursor-pointer items-center gap-2">
                                    <Eye className="size-4 text-muted-foreground" />
                                    <span>{__('View Details')}</span>
                                </Link>
                            </DropdownMenuItem>
                            {canEdit && onEdit && (
                                <DropdownMenuItem onClick={() => onEdit(video)} className="flex cursor-pointer items-center gap-2">
                                    <Edit3 className="size-4 text-muted-foreground" />
                                    <span>{__('Edit Details')}</span>
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuItem onClick={handleCopyId} className="flex cursor-pointer items-center gap-2">
                                <Copy className="size-4 text-muted-foreground" />
                                <span>{__('Copy Video ID')}</span>
                            </DropdownMenuItem>
                            {canDelete && onDelete && (
                                <>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        onClick={() => onDelete(video)}
                                        className="flex cursor-pointer items-center gap-2 text-destructive focus:text-destructive"
                                    >
                                        <Trash2 className="size-4" />
                                        <span>{__('Delete Video')}</span>
                                    </DropdownMenuItem>
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                {/* Footer Metadata */}
                <div className="mt-1 flex items-center justify-between border-t border-border/50 pt-3 text-xs text-muted-foreground">
                    <div className="flex items-center gap-2">{renderVisibility()}</div>
                    {formattedDate && <span title={video.created_at}>{formattedDate}</span>}
                </div>
            </CardContent>
        </Card>
    );
}

export default VideoCard;
