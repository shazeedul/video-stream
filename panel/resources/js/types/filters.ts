export type FilterValue = string | number | boolean | null | undefined;

export interface FilterField {
    key: string;
    type: 'search' | 'select' | 'multiselect' | 'date' | 'daterange' | 'number';
    label: string;
    placeholder?: string;
    options?: FilterOption[];
    defaultValue?: FilterValue;
    debounceMs?: number;
    className?: string;
}

export interface FilterOption {
    value: string | number;
    label: string;
}

export interface FilterConfig {
    fields: FilterField[];
    defaultFilters: Record<string, FilterValue>;
    baseUrl: string;
    preserveState?: boolean;
    preserveScroll?: boolean;
    only?: string[];
    debounceMs?: number;
}

export interface FilterState {
    [key: string]: FilterValue;
}

export interface ActiveFilter {
    key: string;
    label: string;
    value: FilterValue;
    displayValue: string;
}

export interface UseFiltersOptions {
    initialFilters: Record<string, FilterValue>;
    config: FilterConfig;
    onFiltersChange?: (filters: FilterState) => void;
}

export interface UseFiltersReturn {
    filters: FilterState;
    setFilter: (key: string, value: FilterValue) => void;
    setFilters: (filters: Partial<FilterState>) => void;
    clearFilters: () => void;
    activeFilters: ActiveFilter[];
    hasActiveFilters: boolean;
}
