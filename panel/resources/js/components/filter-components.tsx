import FilterInput from '@/components/filter-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { FilterField, FilterValue } from '@/types/filters';
import { useLocalizer } from '@devwizard/laravel-localizer-react';

interface FilterFieldComponentProps {
    field: FilterField;
    value: FilterValue;
    onChange: (value: FilterValue) => void;
}

export function FilterFieldComponent({ field, value, onChange }: FilterFieldComponentProps) {
    const { __ } = useLocalizer();
    const { type, label, placeholder, options, className } = field;

    const renderField = () => {
        switch (type) {
            case 'search':
                return (
                    <FilterInput
                        type="text"
                        placeholder={placeholder}
                        value={String(value || '')}
                        onChange={(e) => onChange(e.target.value)}
                        className={className}
                    />
                );

            case 'select':
                return (
                    <Select value={String(value || '')} onValueChange={onChange}>
                        <SelectTrigger className={className}>
                            <SelectValue placeholder={placeholder || __('Select :label', { label: label.toLowerCase() })} />
                        </SelectTrigger>
                        <SelectContent>
                            {options?.map((option) => (
                                <SelectItem key={option.value} value={String(option.value)}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                );

            case 'number':
                return (
                    <input
                        type="number"
                        placeholder={placeholder || `Enter ${label.toLowerCase()}`}
                        value={String(value || '')}
                        onChange={(e) => onChange(e.target.value ? Number(e.target.value) : '')}
                        className={`w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none ${className || ''}`}
                    />
                );

            case 'date':
                return (
                    <input
                        type="date"
                        value={String(value || '')}
                        onChange={(e) => onChange(e.target.value)}
                        className={`w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none ${className || ''}`}
                    />
                );

            default:
                return null;
        }
    };

    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{label}</label>
            {renderField()}
        </div>
    );
}

interface ActiveFiltersProps {
    activeFilters: Array<{
        key: string;
        label: string;
        value: FilterValue;
        displayValue: string;
    }>;
    onClearFilters: () => void;
    onRemoveFilter?: (key: string) => void;
}

export function ActiveFilters({ activeFilters, onClearFilters, onRemoveFilter }: ActiveFiltersProps) {
    const { __ } = useLocalizer();

    if (activeFilters.length === 0) {
        return null;
    }

    return (
        <div className="flex items-center justify-between pt-4">
            <div className="flex flex-wrap gap-2">
                {activeFilters.map((filter) => (
                    <Badge
                        key={filter.key}
                        variant="secondary"
                        className="cursor-pointer text-xs hover:bg-secondary/80"
                        onClick={() => onRemoveFilter?.(filter.key)}
                    >
                        {filter.label}: {filter.displayValue}
                        {onRemoveFilter && <span className="ml-1 hover:text-destructive">×</span>}
                    </Badge>
                ))}
            </div>
            <Button variant="outline" size="sm" onClick={onClearFilters} className="cursor-pointer">
                {__('Clear Filters')}
            </Button>
        </div>
    );
}
