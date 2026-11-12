import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { VideoStatus } from '@/types/video';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { AlertCircle, Check, CheckCircle2, Clock, FileEdit, Loader2, Package, Search } from 'lucide-react';
import React from 'react';

interface VideoStatusBadgeProps {
    readonly status: VideoStatus | string;
    readonly className?: string;
    readonly showIcon?: boolean;
}

export function VideoStatusBadge({ status, className, showIcon = true }: VideoStatusBadgeProps) {
    const { __ } = useLocalizer();

    const normalizedStatus = status.toLowerCase() as VideoStatus;

    const config: Record<
        VideoStatus,
        {
            label: string;
            className: string;
            icon: React.ComponentType<{ className?: string }>;
            animateIcon?: boolean;
        }
    > = {
        draft: {
            label: __('Draft'),
            className: 'bg-muted text-muted-foreground border-border hover:bg-muted/80',
            icon: FileEdit,
        },
        uploading: {
            label: __('Uploading'),
            className: 'bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-500/20 hover:bg-blue-500/20',
            icon: Loader2,
            animateIcon: true,
        },
        uploaded: {
            label: __('Uploaded'),
            className: 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border-indigo-500/20 hover:bg-indigo-500/20',
            icon: Check,
        },
        queued: {
            label: __('Queued'),
            className: 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/20 hover:bg-amber-500/20',
            icon: Clock,
        },
        probing: {
            label: __('Probing'),
            className: 'bg-purple-500/10 text-purple-700 dark:text-purple-300 border-purple-500/20 hover:bg-purple-500/20',
            icon: Search,
            animateIcon: true,
        },
        transcoding: {
            label: __('Transcoding'),
            className: 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-300 border-cyan-500/20 hover:bg-cyan-500/20',
            icon: Loader2,
            animateIcon: true,
        },
        packaging: {
            label: __('Packaging'),
            className: 'bg-orange-500/10 text-orange-700 dark:text-orange-300 border-orange-500/20 hover:bg-orange-500/20',
            icon: Package,
            animateIcon: true,
        },
        ready: {
            label: __('Ready'),
            className: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20 hover:bg-emerald-500/20',
            icon: CheckCircle2,
        },
        failed: {
            label: __('Failed'),
            className: 'bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-500/20 hover:bg-rose-500/20',
            icon: AlertCircle,
        },
    };

    const currentConfig = config[normalizedStatus] ?? {
        label: status,
        className: 'bg-muted text-muted-foreground border-border',
        icon: Clock,
    };

    const Icon = currentConfig.icon;

    return (
        <Badge
            variant="outline"
            className={cn('inline-flex items-center gap-1.5 font-medium shadow-none transition-colors', currentConfig.className, className)}
        >
            {showIcon && <Icon className={cn('size-3 shrink-0', currentConfig.animateIcon && 'animate-spin')} />}
            <span>{currentConfig.label}</span>
        </Badge>
    );
}

export default VideoStatusBadge;
