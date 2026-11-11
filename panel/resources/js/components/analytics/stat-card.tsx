import { ReactNode } from 'react';

interface StatCardProps {
    title: string;
    value: number | string;
    description?: string;
    icon?: ReactNode;
    className?: string;
}

export function StatCard({ title, value, description, icon, className = '' }: StatCardProps) {
    return (
        <div className={`relative overflow-hidden rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border ${className}`}>
            <div className="flex items-center justify-between">
                <div className="flex-1">
                    <p className="text-sm font-medium text-muted-foreground">{title}</p>
                    <p className="text-2xl font-bold">{value}</p>
                    {description && <p className="mt-1 text-xs text-muted-foreground">{description}</p>}
                </div>
                {icon && <div className="text-muted-foreground">{icon}</div>}
            </div>
        </div>
    );
}

export function SkeletonCard({ className = '' }: { className?: string }) {
    return (
        <div className={`relative overflow-hidden rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border ${className}`}>
            <div className="flex items-center justify-between">
                <div className="flex-1 space-y-2">
                    <div className="h-4 animate-pulse rounded bg-muted" />
                    <div className="h-8 w-2/3 animate-pulse rounded bg-muted" />
                    <div className="h-3 w-1/2 animate-pulse rounded bg-muted" />
                </div>
                <div className="h-6 w-6 animate-pulse rounded bg-muted" />
            </div>
        </div>
    );
}
