import type { NavItem } from '@/types';
import { getCurrentUrl, isActiveUrl } from '@/utils/navigation';
import { useEffect, useMemo, useState } from 'react';

/**
 * Optimized hook to get current URL with minimal re-renders
 */
export function useCurrentUrl(): string {
    const [currentUrl, setCurrentUrl] = useState<string>(() => getCurrentUrl());

    useEffect(() => {
        const handlePopState = () => setCurrentUrl(getCurrentUrl());
        window.addEventListener('popstate', handlePopState);
        return () => window.removeEventListener('popstate', handlePopState);
    }, []);

    return currentUrl;
}

/**
 * Optimized hook to check if nav item is active (memoized)
 */
export function useNavItemActive(item: NavItem): boolean {
    const currentUrl = useCurrentUrl();

    return useMemo(() => {
        // Check active_when pattern first
        if (item.active_when) {
            return isActiveUrl(currentUrl, item.active_when);
        }

        // Check href
        if (item.href && item.href !== '#') {
            const href = typeof item.href === 'string' ? item.href : item.href.url;
            return isActiveUrl(currentUrl, href);
        }

        return false;
    }, [currentUrl, item.active_when, item.href]);
}
