import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { MoreHorizontal } from 'lucide-react';
import { type ReactNode } from 'react';

export interface TableColumn<T> {
    key: string;
    label: string;
    render: (item: T) => ReactNode;
    className?: string;
    headerClassName?: string;
}

export interface TableAction<T> {
    label: string;
    icon?: ReactNode;
    onClick: (item: T) => void;
    variant?: 'default' | 'destructive' | 'success' | 'warning';
    permission?: string;
    show?: (item: T) => boolean;
    separator?: boolean;
}

interface DataTableProps<T> {
    data: T[];
    columns: TableColumn<T>[];
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
    onRowClick?: (item: T) => void;
}

export function DataTable<T extends { id: number | string }>({ data, columns, actions, emptyState, onRowClick }: DataTableProps<T>) {
    const { __ } = useLocalizer();

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

    return (
        <div className="overflow-hidden rounded-md border">
            <table className="w-full">
                <thead className="bg-muted/50">
                    <tr>
                        {columns.map((column) => (
                            <th key={column.key} className={column.headerClassName || 'px-4 py-3 text-left text-sm font-medium'}>
                                {column.label}
                            </th>
                        ))}
                        {actions && actions.length > 0 && <th className="px-4 py-3 text-right text-sm font-medium">{__('Actions')}</th>}
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {data.map((item) => (
                        <tr
                            key={item.id}
                            className={onRowClick ? 'cursor-pointer hover:bg-muted/25' : 'hover:bg-muted/25'}
                            onClick={() => onRowClick?.(item)}
                        >
                            {columns.map((column) => (
                                <td key={column.key} className={column.className || 'px-4 py-3'}>
                                    {column.render(item)}
                                </td>
                            ))}
                            {actions && actions.length > 0 && (
                                <td className="px-4 py-3" onClick={(e) => e.stopPropagation()}>
                                    <div className="flex items-center justify-end">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button size="sm" variant="ghost" className="cursor-pointer">
                                                    <MoreHorizontal className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                {actions
                                                    .filter((action) => !action.show || action.show(item))
                                                    .map((action, index) => (
                                                        <DropdownMenuItem
                                                            key={index}
                                                            onClick={() => action.onClick(item)}
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
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
