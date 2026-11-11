import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Can, usePermissions } from '@devwizard/laravel-react-permissions';
import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { type ReactNode, useCallback, useMemo, useState } from 'react';

export interface TabNavigationProps {
    /**
     * Custom header content (e.g., user info, logo, etc.)
     */
    header?: ReactNode;

    /**
     * Page title displayed at the top
     */
    title?: string;

    /**
     * Page description displayed below the title
     */
    description?: string;

    /**
     * Main navigation tabs using NavItem interface
     * Can use either 'tabs' or 'navigation' prop
     */
    tabs?: NavItem[];
    navigation?: NavItem[];

    /**
     * Optional action button or element displayed in the header
     */
    action?: ReactNode;

    /**
     * Page content to render
     */
    children: ReactNode;

    /**
     * Optional className for the container
     */
    className?: string;

    /**
     * Optional className for the content area
     */
    contentClassName?: string;
}

/**
 * Recursive navigation item component that supports infinite nesting
 */
function NavItemComponent({
    item,
    isActive,
    isNavItemActive,
    level = 0,
}: Readonly<{
    item: NavItem;
    isActive: boolean;
    isNavItemActive: (item: NavItem) => boolean;
    level?: number;
}>) {
    const hasChildren = item.items && item.items.length > 0;
    const hasActiveChild = hasChildren && item.items!.some((child) => isNavItemActive(child));
    const [isOpen, setIsOpen] = useState(isActive || hasActiveChild);

    if (!hasChildren) {
        return (
            <Can permission={item.permission || '*'}>
                <Link
                    href={item.href}
                    prefetch
                    className={cn(
                        'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground',
                        isActive ? 'bg-accent text-accent-foreground' : 'text-muted-foreground',
                    )}
                    style={{ paddingLeft: `${0.75 + level * 0.75}rem` }}
                >
                    {item.icon && <item.icon className="h-4 w-4" />}
                    {item.title}
                </Link>
            </Can>
        );
    }

    return (
        <Can permission={item.permission || '*'}>
            <div>
                <button
                    type="button"
                    onClick={() => setIsOpen(!isOpen)}
                    className={cn(
                        'flex w-full items-center justify-between gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground',
                        isActive ? 'bg-accent text-accent-foreground' : 'text-muted-foreground',
                    )}
                    style={{ paddingLeft: `${0.75 + level * 0.75}rem` }}
                >
                    <span className="flex items-center gap-2">
                        {item.icon && <item.icon className="h-4 w-4" />}
                        {item.title}
                    </span>
                    <ChevronRight className={cn('h-4 w-4 transition-transform', isOpen && 'rotate-90')} />
                </button>
                {isOpen && (
                    <div className="mt-1 space-y-1">
                        {item.items!.map((child) => (
                            <NavItemComponent
                                key={child.title}
                                item={child}
                                isActive={isNavItemActive(child)}
                                isNavItemActive={isNavItemActive}
                                level={level + 1}
                            />
                        ))}
                    </div>
                )}
            </div>
        </Can>
    );
}

/**
 * Mobile dropdown navigation for child items
 */
function MobileChildNavigation({
    items,
    isNavItemActive,
}: Readonly<{
    items: NavItem[];
    isNavItemActive: (item: NavItem) => boolean;
}>) {
    const [isOpen, setIsOpen] = useState(false);
    const activeItem = items.find((item) => isNavItemActive(item));

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="flex w-full items-center justify-between rounded-md bg-muted/50 px-3 py-2 text-sm font-medium"
            >
                <span className="flex items-center gap-2">
                    {activeItem?.icon && <activeItem.icon className="h-4 w-4" />}
                    {activeItem ? activeItem.title : 'Menu'}
                </span>
                <ChevronDown className={cn('h-4 w-4 transition-transform', isOpen && 'rotate-180')} />
            </button>
            {isOpen && (
                <div className="mt-2 space-y-1 rounded-md border bg-background p-2 shadow-lg">
                    {items.map((child) => (
                        <NavItemComponent
                            key={child.title}
                            item={child}
                            isActive={isNavItemActive(child)}
                            isNavItemActive={isNavItemActive}
                            level={0}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

export default function TabNavigation({
    header,
    title,
    description,
    tabs,
    navigation,
    action,
    children,
    className,
    contentClassName,
}: Readonly<TabNavigationProps>) {
    const page = usePage();
    const currentPath = page.url;
    const { hasPermission } = usePermissions();

    // Use navigation if provided, otherwise fall back to tabs
    const navItems = useMemo(() => navigation || tabs || [], [navigation, tabs]);

    /**
     * Calculate parent permission by combining child permissions with OR operator
     * This allows access to parent if user has access to ANY child
     */
    const getParentPermission = useCallback((item: NavItem): string | undefined => {
        if (!item.items || item.items.length === 0) {
            return item.permission;
        }

        const childPermissions = item.items
            .map((child) => child.permission)
            .filter(Boolean)
            .filter((perm) => perm !== '*');

        if (childPermissions.length === 0) {
            return item.permission || '*';
        }

        return childPermissions.join(' || ');
    }, []);

    /**
     * Permission cache to avoid redundant permission checks
     */
    const permissionCache = useMemo(() => {
        const cache = new Map<string, boolean>();

        const calculateItemPermissions = (items: NavItem[]) => {
            for (const item of items) {
                const permission = getParentPermission(item);
                const cacheKey = permission || '*';

                if (!cache.has(cacheKey)) {
                    cache.set(cacheKey, !permission || permission === '*' ? true : hasPermission(permission));
                }

                if (item.items) {
                    calculateItemPermissions(item.items);
                }
            }
        };

        calculateItemPermissions(navItems);
        return cache;
    }, [navItems, hasPermission, getParentPermission]);

    /**
     * Updated isItemAccessible to use permission cache
     */
    const isItemAccessible = useCallback(
        (item: NavItem): boolean => {
            const permission = getParentPermission(item);
            const cacheKey = permission || '*';
            return permissionCache.get(cacheKey) ?? true;
        },
        [getParentPermission, permissionCache],
    );

    /**
     * Get the first accessible child item
     */
    const getFirstAccessibleChild = useCallback(
        (item: NavItem): NavItem | null => {
            if (!item.items || item.items.length === 0) return null;

            for (const child of item.items) {
                if (isItemAccessible(child)) {
                    return child;
                }
                // Check recursively if child has children
                if (child.items && child.items.length > 0) {
                    const accessibleGrandchild = getFirstAccessibleChild(child);
                    if (accessibleGrandchild) return accessibleGrandchild;
                }
            }
            return null;
        },
        [isItemAccessible],
    );

    /**
     * Get the effective href for a navigation item
     * If item has children and user doesn't have permission to parent,
     * use the first accessible child's href
     */
    const getEffectiveHref = useCallback(
        (item: NavItem) => {
            // If item has children, find the first accessible child
            if (item.items && item.items.length > 0) {
                const firstAccessibleChild = getFirstAccessibleChild(item);
                if (firstAccessibleChild) {
                    return firstAccessibleChild.href;
                }
            }
            // Otherwise use the item's own href
            return item.href;
        },
        [getFirstAccessibleChild],
    );

    /**
     * Helper function to check if a URL matches the active_when pattern
     * Supports wildcards (*)
     */
    const matchesPattern = (pattern: string, path: string): boolean => {
        const regexPattern = pattern.replaceAll('*', '.*').replaceAll('/', String.raw`\/`);
        const regex = new RegExp(`^${regexPattern}$`);
        return regex.test(path);
    };

    /**
     * Helper function to check if a nav item is active
     */
    const isNavItemActive = (item: NavItem): boolean => {
        // Check explicit is_active flag
        if (item.is_active) {
            return true;
        }

        // Check active_when pattern if provided
        if (item.active_when) {
            if (matchesPattern(item.active_when, currentPath)) {
                return true;
            }
        }

        // Check exact href match
        const itemHref = typeof item.href === 'string' ? item.href : item.href.url;
        if (currentPath === itemHref) {
            return true;
        }

        // Check if current path matches any child item
        if (item.items) {
            return item.items.some((child) => isNavItemActive(child));
        }

        return false;
    };

    // Find the active tab from nav items
    const activeTabIndex = navItems.findIndex((tab) => isNavItemActive(tab));
    const activeTabObject = activeTabIndex >= 0 ? navItems[activeTabIndex] : navItems[0];

    // Get child items from active tab
    const childItems = activeTabObject?.items || [];

    // Check if active tab has children
    const hasChildNav = childItems && childItems.length > 0;

    return (
        <div className={cn('flex min-h-screen flex-col', className)}>
            {/* Header Section */}
            {(header || title) && (
                <div className="border-b bg-background">
                    <div className="container mx-auto px-4 py-6">
                        {header || (
                            <div className="flex items-start justify-between">
                                <div>
                                    {title && <h1 className="text-2xl font-bold tracking-tight">{title}</h1>}
                                    {description && <p className="mt-1 text-muted-foreground">{description}</p>}
                                </div>
                                {action && <div className="flex items-center gap-2">{action}</div>}
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Main Navigation Tabs */}
            <div className="border-b bg-background">
                <div className="container mx-auto px-4">
                    <Tabs value={activeTabIndex.toString()} className="w-full">
                        <TabsList className="h-12 w-full justify-start rounded-none border-0 bg-transparent p-0">
                            {navItems.map((tab, index) => {
                                const effectiveHref = getEffectiveHref(tab);
                                return (
                                    <Can key={tab.title} permission={tab.permission || '*'}>
                                        <TabsTrigger
                                            value={index.toString()}
                                            asChild
                                            className="relative rounded-none border-b-2 border-transparent px-4 py-3 data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                        >
                                            <Link href={effectiveHref} prefetch>
                                                {tab.icon && <tab.icon className="mr-2 h-4 w-4" />}
                                                {tab.title}
                                            </Link>
                                        </TabsTrigger>
                                    </Can>
                                );
                            })}
                        </TabsList>
                    </Tabs>

                    {/* Mobile Child Navigation Dropdown - shown below tabs on mobile */}
                    {hasChildNav && childItems && (
                        <div className="block border-t py-2 lg:hidden">
                            <MobileChildNavigation items={childItems} isNavItemActive={isNavItemActive} />
                        </div>
                    )}
                </div>
            </div>

            {/* Content Area with Optional Child Navigation */}
            <div className="flex flex-1">
                {/* Child Navigation Sidebar (only shown if active tab has children) - Hidden on mobile */}
                {hasChildNav && childItems && (
                    <aside className="hidden w-48 border-r bg-muted/30 lg:block">
                        <nav className="sticky top-0 flex flex-col gap-1 p-4">
                            {childItems.map((child) => (
                                <NavItemComponent
                                    key={child.title}
                                    item={child}
                                    isActive={isNavItemActive(child)}
                                    isNavItemActive={isNavItemActive}
                                    level={0}
                                />
                            ))}
                        </nav>
                    </aside>
                )}

                {/* Main Content */}
                <main className={cn('flex-1', contentClassName)}>
                    <div className="container mx-auto p-6">{children}</div>
                </main>
            </div>
        </div>
    );
}
