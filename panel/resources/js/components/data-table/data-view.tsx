import { useViewMode } from '@/components/view-mode-toggle';
import { type ReactNode } from 'react';
import { DataGrid } from './data-grid';
import { DataTable, type TableAction, type TableColumn } from './data-table';

interface DataViewProps<T> {
    data: T[];
    // Table view props
    columns: TableColumn<T>[];
    // Grid view props
    renderCard: (item: T) => ReactNode;
    gridColumns?: 2 | 3 | 4;
    // Common props
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
    storageKey?: string;
    defaultView?: 'grid' | 'list';
}

export function DataView<T extends { id: number | string }>({
    data,
    columns,
    renderCard,
    gridColumns = 4,
    actions,
    emptyState,
    onRowClick,
    storageKey = 'data-view-mode',
    defaultView = 'grid',
}: DataViewProps<T>) {
    const [viewMode] = useViewMode(storageKey, defaultView);

    if (viewMode === 'list') {
        return <DataTable data={data} columns={columns} actions={actions} emptyState={emptyState} onRowClick={onRowClick} />;
    }

    return <DataGrid data={data} renderCard={renderCard} actions={actions} emptyState={emptyState} onCardClick={onRowClick} columns={gridColumns} />;
}
