import { Button } from '@/components/ui/button';
import { Grid3X3, List } from 'lucide-react';
import { useEffect, useState } from 'react';

export type ViewMode = 'list' | 'grid';

interface ViewModeToggleProps {
    storageKey?: string;
    defaultMode?: ViewMode;
    onChange?: (mode: ViewMode) => void;
    className?: string;
}

export function ViewModeToggle({ storageKey = 'view-mode', defaultMode = 'grid', onChange, className }: ViewModeToggleProps) {
    const [viewMode, setViewMode] = useState<ViewMode>(() => {
        if (typeof window === 'undefined') return defaultMode;

        const stored = localStorage.getItem(storageKey);
        return (stored as ViewMode) || defaultMode;
    });

    const handleModeChange = (mode: ViewMode) => {
        setViewMode(mode);
        localStorage.setItem(storageKey, mode);

        // Dispatch custom event for other components to listen
        window.dispatchEvent(
            new CustomEvent('viewModeChange', {
                detail: { storageKey, mode },
            }),
        );

        onChange?.(mode);
    };

    useEffect(() => {
        onChange?.(viewMode);
    }, [viewMode, onChange]);

    return (
        <div className={`flex items-center gap-2 ${className || ''}`}>
            <Button variant={viewMode === 'list' ? 'default' : 'outline'} size="sm" onClick={() => handleModeChange('list')} aria-label="List view">
                <List className="size-4" />
            </Button>
            <Button variant={viewMode === 'grid' ? 'default' : 'outline'} size="sm" onClick={() => handleModeChange('grid')} aria-label="Grid view">
                <Grid3X3 className="size-4" />
            </Button>
        </div>
    );
}

export function useViewMode(storageKey: string = 'view-mode', defaultMode: ViewMode = 'grid'): [ViewMode, (mode: ViewMode) => void] {
    const [viewMode, setViewMode] = useState<ViewMode>(() => {
        if (typeof window === 'undefined') return defaultMode;

        const stored = localStorage.getItem(storageKey);
        return (stored as ViewMode) || defaultMode;
    });

    // Listen for storage changes from other components
    useEffect(() => {
        const handleStorageChange = (e: StorageEvent) => {
            if (e.key === storageKey && e.newValue) {
                setViewMode(e.newValue as ViewMode);
            }
        };

        // Listen for custom event (for same-window changes)
        const handleViewModeChange = (e: CustomEvent<{ storageKey: string; mode: ViewMode }>) => {
            if (e.detail.storageKey === storageKey) {
                setViewMode(e.detail.mode);
            }
        };

        window.addEventListener('storage', handleStorageChange);
        window.addEventListener('viewModeChange', handleViewModeChange as EventListener);

        return () => {
            window.removeEventListener('storage', handleStorageChange);
            window.removeEventListener('viewModeChange', handleViewModeChange as EventListener);
        };
    }, [storageKey]);

    const updateViewMode = (mode: ViewMode) => {
        setViewMode(mode);
        localStorage.setItem(storageKey, mode);

        // Dispatch custom event for same-window components
        window.dispatchEvent(
            new CustomEvent('viewModeChange', {
                detail: { storageKey, mode },
            }),
        );
    };

    return [viewMode, updateViewMode];
}
