import { ActiveFilters, FilterFieldComponent } from '@/components/filter-components';
import { ViewModeToggle, type ViewMode } from '@/components/view-mode-toggle';
import { useFilters } from '@/hooks/use-filters';
import type { FilterConfig, FilterValue } from '@/types/filters';
import { useCallback } from 'react';

interface FilterPanelProps {
    initialFilters: Record<string, FilterValue>;
    config: FilterConfig;
    onFiltersChange?: (filters: Record<string, FilterValue>) => void;
    viewMode?: ViewMode;
    storageKey?: string;
    className?: string;
}

export default function FilterPanel({ initialFilters, config, onFiltersChange, viewMode, storageKey, className = '' }: FilterPanelProps) {
    const { filters, setFilter, clearFilters, activeFilters, hasActiveFilters } = useFilters({
        initialFilters,
        config,
        onFiltersChange,
    });

    // Memoize the filter removal handler to prevent unnecessary re-renders
    const handleRemoveFilter = useCallback(
        (key: string) => {
            const defaultValue = config.defaultFilters[key];
            setFilter(key, defaultValue);
        },
        [config.defaultFilters, setFilter],
    );

    return (
        <div className={`rounded-lg border bg-card p-4 ${className}`}>
            {/* Filter Fields Grid with View Mode Toggle */}
            <div className="flex flex-col gap-4">
                <div className="flex items-center justify-between">
                    <div className="grid flex-1 grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {config.fields.map((field) => (
                            <FilterFieldComponent
                                key={field.key}
                                field={field}
                                value={filters[field.key]}
                                onChange={(value) => setFilter(field.key, value)}
                            />
                        ))}
                    </div>
                    {viewMode !== undefined && <ViewModeToggle storageKey={storageKey} defaultMode={viewMode} className="ml-4" />}
                </div>

                {/* Active Filters and Clear Button */}
                {hasActiveFilters && (
                    <ActiveFilters activeFilters={activeFilters} onClearFilters={clearFilters} onRemoveFilter={handleRemoveFilter} />
                )}
            </div>
        </div>
    );
}
