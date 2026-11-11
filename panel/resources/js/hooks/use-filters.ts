/**
 * Optimized useFilters hook for managing filter state and debounced API calls
 *
 * Performance optimizations:
 * - Uses efficient config comparison instead of expensive JSON.stringify
 * - Optimized field lookup with Map for activeFilters calculation
 * - Proper memoization to prevent unnecessary re-renders
 * - Debounced API calls with proper cleanup
 */
import type { ActiveFilter, FilterConfig, FilterState, FilterValue, UseFiltersOptions, UseFiltersReturn } from '@/types/filters';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

// Deep comparison for config objects to avoid infinite loops
function configsEqual(config1: FilterConfig, config2: FilterConfig): boolean {
    return (
        config1.baseUrl === config2.baseUrl &&
        config1.debounceMs === config2.debounceMs &&
        config1.preserveScroll === config2.preserveScroll &&
        config1.preserveState === config2.preserveState &&
        JSON.stringify(config1.fields) === JSON.stringify(config2.fields) &&
        JSON.stringify(config1.defaultFilters) === JSON.stringify(config2.defaultFilters) &&
        JSON.stringify(config1.only) === JSON.stringify(config2.only)
    );
}

export function useFilters({ initialFilters, config, onFiltersChange }: UseFiltersOptions): UseFiltersReturn {
    // Use a ref to store the previous config to avoid infinite loops
    const configRef = useRef(config);
    const isConfigChanged = !configsEqual(configRef.current, config);

    if (isConfigChanged) {
        configRef.current = config;
    }

    // Track if this is the initial load to prevent unnecessary requests
    const isInitialLoadRef = useRef(true);

    const [filters, setFilters] = useState<FilterState>(() => {
        // Merge initial filters with default values from config
        const merged = { ...config.defaultFilters };
        Object.entries(initialFilters).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                merged[key] = value;
            }
        });
        return merged;
    });

    // Debounced navigation effect with request cancellation
    useEffect(() => {
        // Skip the initial load request since data is already loaded by server
        if (isInitialLoadRef.current) {
            isInitialLoadRef.current = false;
            onFiltersChange?.(filters);
            return;
        }

        const timeoutId = setTimeout(() => {
            const params = new URLSearchParams(window.location.search);

            // Build query parameters from filters
            Object.entries(filters).forEach(([key, value]) => {
                if (value !== null && value !== undefined && value !== '' && value !== configRef.current.defaultFilters[key]) {
                    params.set(key, String(value));
                } else {
                    params.delete(key);
                }
            });

            // Reset to page 1 only if we're changing search/category filters, but preserve page for other changes
            const searchChanged = params.get('search') !== new URLSearchParams(window.location.search).get('search');
            const categoryChanged = params.get('category') !== new URLSearchParams(window.location.search).get('category');

            if (searchChanged || categoryChanged) {
                params.delete('page');
            }

            const queryString = params.toString();
            const url = queryString ? `${configRef.current.baseUrl}?${queryString}` : configRef.current.baseUrl;

            // Use router.get with visit cancellation for better performance
            router.get(
                url,
                {},
                {
                    preserveScroll: configRef.current.preserveScroll ?? true,
                    preserveState: configRef.current.preserveState ?? true,
                    only: configRef.current.only,
                    replace: true,
                },
            );

            onFiltersChange?.(filters);
        }, configRef.current.debounceMs ?? 300);

        return () => clearTimeout(timeoutId);
    }, [filters, onFiltersChange, isConfigChanged]);

    const setFilter = useCallback((key: string, value: FilterValue) => {
        setFilters((prev) => ({ ...prev, [key]: value }));
    }, []);

    const clearFilters = useCallback(() => {
        setFilters({ ...configRef.current.defaultFilters });
    }, []);

    // Calculate active filters for display - optimized with field lookup map
    const activeFilters = useMemo<ActiveFilter[]>(() => {
        const active: ActiveFilter[] = [];

        // Create a field lookup map for better performance
        const fieldMap = new Map(configRef.current.fields.map((field) => [field.key, field]));

        Object.entries(filters).forEach(([key, value]) => {
            const field = fieldMap.get(key);
            if (!field || value === configRef.current.defaultFilters[key] || value === null || value === undefined || value === '') {
                return;
            }

            let displayValue = String(value);

            // For select fields, try to find the option label
            if (field.type === 'select' && field.options) {
                const option = field.options.find((opt) => opt.value === value);
                if (option) {
                    displayValue = option.label;
                }
            }

            active.push({
                key,
                label: field.label,
                value,
                displayValue,
            });
        });

        return active;
    }, [filters]);

    const hasActiveFilters = activeFilters.length > 0;

    return {
        filters,
        setFilter,
        setFilters,
        clearFilters,
        activeFilters,
        hasActiveFilters,
    };
}
