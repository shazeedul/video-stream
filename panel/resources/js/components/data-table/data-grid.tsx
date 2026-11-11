import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { MoreHorizontal } from 'lucide-react';
import { type ReactNode } from 'react';
import { type TableAction } from './data-table';

interface DataGridProps<T> {
    data: T[];
    renderCard: (item: T) => ReactNode;
    actions?: TableAction<T>[];
    emptyState?: {
        icon?: ReactNode;
        title: string;
        description: string;
        action?: {
            label: string;
            onClick: () => void;
            permission?: string;
        };
    };
    onCardClick?: (item: T) => void;
    columns?: 2 | 3 | 4;
}

export function DataGrid<T extends { id: number | string }>({ data, renderCard, actions, emptyState, onCardClick, columns = 4 }: DataGridProps<T>) {
    if (data.length === 0 && emptyState) {
        return (
            <div className="rounded-lg border border-dashed p-8 text-center">
                {emptyState.icon && <div className="mx-auto mb-4">{emptyState.icon}</div>}
                <h3 className="text-lg font-medium">{emptyState.title}</h3>
                <p className="mt-2 text-sm text-muted-foreground">{emptyState.description}</p>
                {emptyState.action && (
                    <Button onClick={emptyState.action.onClick} className="mt-4">
                        {emptyState.action.label}
                    </Button>
                )}
            </div>
        );
    }

    const gridClass =
        columns === 2
            ? 'grid gap-4 sm:grid-cols-2'
            : columns === 3
              ? 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3'
              : 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4';

    return (
        <div className={gridClass}>
            {data.map((item) => (
                <Card
                    key={item.id}
                    className={`group transition-all hover:border-primary hover:shadow-md ${onCardClick ? 'cursor-pointer' : ''}`}
                    onClick={() => onCardClick?.(item)}
                >
                    <div className="relative p-6">
                        {actions && actions.length > 0 && (
                            <div className="absolute top-4 right-4" onClick={(e) => e.stopPropagation()}>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button size="sm" variant="ghost" className="h-8 w-8 p-0">
                                            <MoreHorizontal className="h-4 w-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        {actions
                                            .filter((action) => !action.show || action.show(item))
                                            .map((action, index) => (
                                                <DropdownMenuItem
                                                    key={index}
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        action.onClick(item);
                                                    }}
                                                    className={`cursor-pointer ${
                                                        action.variant === 'destructive'
                                                            ? 'text-destructive focus:text-destructive'
                                                            : action.variant === 'success'
                                                              ? 'text-green-600 focus:text-green-600'
                                                              : action.variant === 'warning'
                                                                ? 'text-amber-600 focus:text-amber-600'
                                                                : ''
                                                    }`}
                                                >
                                                    {action.icon && <span className="mr-2">{action.icon}</span>}
                                                    {action.label}
                                                </DropdownMenuItem>
                                            ))}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        )}
                        {renderCard(item)}
                    </div>
                </Card>
            ))}
        </div>
    );
}
