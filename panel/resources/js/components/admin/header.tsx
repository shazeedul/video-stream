import { AdminMenuContent } from '@/components/admin/menu-content';
import { AdminNotificationDropdown } from '@/components/admin/notification-dropdown';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { GlobalSearch } from '@/components/global-search';
import { Icon } from '@/components/icon';
import { LanguageSwitcher } from '@/components/language-switcher';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import {
    NavigationMenu,
    NavigationMenuContent,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuList,
    NavigationMenuTrigger,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useAdminMainNavigation } from '@/config/useAdminNavigation';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import { type BreadcrumbItem, type NavItem, type SharedData } from '@/types';
import { getCurrentUrl, isActiveUrl } from '@/utils/navigation';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Can, usePermissions } from '@devwizard/laravel-react-permissions';
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, Menu, Search } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import AppLogo from '../app-logo';

const activeItemStyles = 'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100';

interface HeaderProps {
    breadcrumbs?: BreadcrumbItem[];
}

export function Header({ breadcrumbs = [] }: HeaderProps) {
    const page = usePage<SharedData>();
    const { auth } = page.props;
    const getInitials = useInitials();
    const [searchOpen, setSearchOpen] = useState(false);
    const currentUrl = getCurrentUrl();
    const { hasPermission } = usePermissions();
    const { __ } = useLocalizer();
    const { navGroups, footerNavItems } = useAdminMainNavigation();

    // Get all navigation items from groups, preserving structure
    const mainNavItems = useMemo(() => {
        const items: NavItem[] = [];
        navGroups.forEach((group) => {
            items.push(...group.items);
        });
        return items;
    }, [navGroups]);

    // Filter accessible items based on permissions
    const accessibleMainNavItems = useMemo(() => {
        return mainNavItems.filter((item) => {
            // If item has sub-items, check if any sub-item is accessible
            if (item.items && item.items.length > 0) {
                const accessibleSubItems = item.items.filter((subItem) => {
                    if (!subItem.permission || subItem.permission === '*') return true;
                    return hasPermission(subItem.permission);
                });
                return accessibleSubItems.length > 0;
            }
            // For items without sub-items, check their permission
            if (!item.permission || item.permission === '*') return true;
            return hasPermission(item.permission);
        });
    }, [mainNavItems, hasPermission]);

    // Get accessible sub-items for a parent item
    const getAccessibleSubItems = useCallback(
        (item: NavItem): NavItem[] => {
            if (!item.items) return [];
            return item.items.filter((subItem) => {
                if (!subItem.permission || subItem.permission === '*') return true;
                return hasPermission(subItem.permission);
            });
        },
        [hasPermission],
    );

    // Check if an item is active
    const isItemActive = useCallback(
        (item: NavItem): boolean => {
            if (item.is_active) return true;

            if (item.active_when) {
                return isActiveUrl(currentUrl, item.active_when);
            }

            if (item.href && item.href !== '#') {
                const href = typeof item.href === 'string' ? item.href : item.href.url;
                return isActiveUrl(currentUrl, href);
            }

            return false;
        },
        [currentUrl],
    );

    // Check if any child item is active (for parent menu highlighting)
    const hasActiveChild = useCallback(
        (item: NavItem): boolean => {
            if (!item.items || item.items.length === 0) return false;

            return item.items.some((subItem) => {
                if (subItem.is_active) return true;

                if (subItem.active_when) {
                    return isActiveUrl(currentUrl, subItem.active_when);
                }

                if (subItem.href && subItem.href !== '#') {
                    const href = typeof subItem.href === 'string' ? subItem.href : subItem.href.url;
                    return isActiveUrl(currentUrl, href);
                }

                return false;
            });
        },
        [currentUrl],
    );

    // Keyboard shortcut for search (Cmd+K or Ctrl+K)
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                setSearchOpen(true);
            }
        };

        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, []);

    return (
        <>
            <div className="border-b border-sidebar-border/80">
                <div className="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
                    {/* Mobile Menu */}
                    <div className="lg:hidden">
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button variant="ghost" size="icon" className="mr-2 h-[34px] w-[34px]">
                                    <Menu className="h-5 w-5" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="left" className="flex h-full w-64 flex-col items-stretch justify-between bg-sidebar">
                                <SheetTitle className="sr-only">{__('Navigation Menu')}</SheetTitle>
                                <SheetHeader className="flex justify-start text-left">
                                    <AppLogo className="max-w-48 fill-current text-black dark:text-white" />
                                </SheetHeader>
                                <div className="flex h-full flex-1 flex-col space-y-4 p-4">
                                    <div className="flex h-full flex-col justify-between text-sm">
                                        <div className="flex flex-col space-y-2">
                                            {accessibleMainNavItems.map((item) => {
                                                const hasSubItems = item.items && item.items.length > 0;
                                                const accessibleSubItems = hasSubItems ? getAccessibleSubItems(item) : [];
                                                const isParentActive = isItemActive(item) || hasActiveChild(item);

                                                if (hasSubItems && accessibleSubItems.length > 0) {
                                                    // Render collapsible parent item with sub-items
                                                    return (
                                                        <Can key={item.title} permission={item.permission ?? '*'}>
                                                            <Collapsible defaultOpen={isParentActive}>
                                                                <CollapsibleTrigger className="flex w-full items-center justify-between rounded-md px-2 py-2 font-medium hover:bg-accent">
                                                                    <div className="flex items-center space-x-2">
                                                                        {item.icon && <Icon iconNode={item.icon} className="h-5 w-5" />}
                                                                        <span>{item.title}</span>
                                                                    </div>
                                                                    <ChevronRight className="h-4 w-4 transition-transform duration-200 data-[state=open]:rotate-90" />
                                                                </CollapsibleTrigger>
                                                                <CollapsibleContent className="mt-1">
                                                                    <div className="ml-7 flex flex-col space-y-1">
                                                                        {accessibleSubItems.map((subItem) => (
                                                                            <Can key={subItem.title} permission={subItem.permission ?? '*'}>
                                                                                <Link
                                                                                    href={subItem.href}
                                                                                    className={cn(
                                                                                        'flex items-center space-x-2 rounded-md px-2 py-2 font-medium transition-colors hover:bg-accent',
                                                                                        isItemActive(subItem) && 'bg-accent text-accent-foreground',
                                                                                    )}
                                                                                >
                                                                                    {subItem.icon && (
                                                                                        <Icon iconNode={subItem.icon} className="h-4 w-4" />
                                                                                    )}
                                                                                    <span>{subItem.title}</span>
                                                                                </Link>
                                                                            </Can>
                                                                        ))}
                                                                    </div>
                                                                </CollapsibleContent>
                                                            </Collapsible>
                                                        </Can>
                                                    );
                                                }

                                                // Render regular link for items without sub-items
                                                return (
                                                    <Can key={item.title} permission={item.permission ?? '*'}>
                                                        <Link
                                                            href={item.href}
                                                            className={cn(
                                                                'flex items-center space-x-2 rounded-md px-2 py-2 font-medium transition-colors hover:bg-accent',
                                                                isItemActive(item) && 'bg-accent text-accent-foreground',
                                                            )}
                                                        >
                                                            {item.icon && <Icon iconNode={item.icon} className="h-5 w-5" />}
                                                            <span>{item.title}</span>
                                                        </Link>
                                                    </Can>
                                                );
                                            })}
                                        </div>

                                        <div className="flex flex-col space-y-4">
                                            {footerNavItems.map((item) => (
                                                <Can key={item.title} permission={item.permission ?? '*'}>
                                                    <a
                                                        href={typeof item.href === 'string' ? item.href : item.href.url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="flex items-center space-x-2 font-medium"
                                                    >
                                                        {item.icon && <Icon iconNode={item.icon} className="h-5 w-5" />}
                                                        <span>{item.title}</span>
                                                    </a>
                                                </Can>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </SheetContent>
                        </Sheet>
                    </div>

                    <Link href={dashboard()} prefetch className="flex items-center space-x-2">
                        <AppLogo className="max-w-48" />
                    </Link>

                    {/* Desktop Navigation */}
                    <div className="ml-6 hidden h-full items-center space-x-6 lg:flex">
                        <NavigationMenu className="flex h-full items-stretch">
                            <NavigationMenuList className="flex h-full items-stretch space-x-2">
                                {accessibleMainNavItems.map((item) => {
                                    const hasSubItems = item.items && item.items.length > 0;
                                    const accessibleSubItems = hasSubItems ? getAccessibleSubItems(item) : [];
                                    const isParentActive = isItemActive(item) || hasActiveChild(item);

                                    if (hasSubItems && accessibleSubItems.length > 0) {
                                        // Render dropdown menu for items with sub-items
                                        return (
                                            <Can key={item.title} permission={item.permission ?? '*'}>
                                                <NavigationMenuItem className="relative flex h-full items-center">
                                                    <NavigationMenuTrigger
                                                        className={cn('h-9 cursor-pointer px-3', isParentActive && activeItemStyles)}
                                                    >
                                                        {item.icon && <Icon iconNode={item.icon} className="mr-2 h-4 w-4" />}
                                                        {item.title}
                                                    </NavigationMenuTrigger>
                                                    <NavigationMenuContent>
                                                        <ul className="grid w-[200px] gap-1 p-2">
                                                            {accessibleSubItems.map((subItem) => (
                                                                <Can key={subItem.title} permission={subItem.permission ?? '*'}>
                                                                    <li>
                                                                        <NavigationMenuLink asChild>
                                                                            <Link
                                                                                href={subItem.href}
                                                                                className={cn(
                                                                                    'block space-y-1 rounded-md p-3 leading-none no-underline transition-colors outline-none select-none hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground',
                                                                                    isItemActive(subItem) && 'bg-accent text-accent-foreground',
                                                                                )}
                                                                            >
                                                                                <div className="flex items-center gap-2">
                                                                                    {subItem.icon && (
                                                                                        <Icon iconNode={subItem.icon} className="h-4 w-4" />
                                                                                    )}
                                                                                    <div className="text-sm leading-none font-medium">
                                                                                        {subItem.title}
                                                                                    </div>
                                                                                </div>
                                                                            </Link>
                                                                        </NavigationMenuLink>
                                                                    </li>
                                                                </Can>
                                                            ))}
                                                        </ul>
                                                    </NavigationMenuContent>
                                                    {isParentActive && (
                                                        <div className="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-black dark:bg-white"></div>
                                                    )}
                                                </NavigationMenuItem>
                                            </Can>
                                        );
                                    }

                                    // Render regular link for items without sub-items
                                    return (
                                        <Can key={item.title} permission={item.permission ?? '*'}>
                                            <NavigationMenuItem className="relative flex h-full items-center">
                                                <Link
                                                    href={item.href}
                                                    className={cn(
                                                        navigationMenuTriggerStyle(),
                                                        isItemActive(item) && activeItemStyles,
                                                        'h-9 cursor-pointer px-3',
                                                    )}
                                                >
                                                    {item.icon && <Icon iconNode={item.icon} className="mr-2 h-4 w-4" />}
                                                    {item.title}
                                                </Link>
                                                {isItemActive(item) && (
                                                    <div className="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-black dark:bg-white"></div>
                                                )}
                                            </NavigationMenuItem>
                                        </Can>
                                    );
                                })}
                            </NavigationMenuList>
                        </NavigationMenu>
                    </div>

                    <div className="ml-auto flex items-center space-x-2">
                        <div className="relative flex items-center space-x-1">
                            <TooltipProvider delayDuration={0}>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="group h-9 w-9 cursor-pointer"
                                            onClick={() => setSearchOpen(true)}
                                        >
                                            <Search className="!size-5 opacity-80 group-hover:opacity-100" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p className="flex items-center gap-2">
                                            Search
                                            <kbd className="rounded border bg-muted px-1.5 py-0.5 font-mono text-xs">⌘K</kbd>
                                        </p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                            <LanguageSwitcher />
                            <AdminNotificationDropdown />
                            <div className="hidden lg:flex">
                                {footerNavItems.map((item) => (
                                    <Can key={item.title} permission={item.permission ?? '*'}>
                                        <TooltipProvider delayDuration={0}>
                                            <Tooltip>
                                                <TooltipTrigger>
                                                    <a
                                                        href={typeof item.href === 'string' ? item.href : item.href.url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="group ml-1 inline-flex h-9 w-9 items-center justify-center rounded-md bg-transparent p-0 text-sm font-medium text-accent-foreground ring-offset-background transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                                                    >
                                                        <span className="sr-only">{item.title}</span>
                                                        {item.icon && (
                                                            <Icon iconNode={item.icon} className="size-5 opacity-80 group-hover:opacity-100" />
                                                        )}
                                                    </a>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>{item.title}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </Can>
                                ))}
                            </div>
                        </div>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" className="size-10 rounded-full p-1">
                                    <Avatar className="size-8 overflow-hidden rounded-full">
                                        <AvatarImage src={auth.user.avatar as string} alt={auth.user.name} />
                                        <AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                            {getInitials(auth.user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="w-56" align="end">
                                <AdminMenuContent user={auth.user} />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </div>
            {breadcrumbs.length > 1 && (
                <div className="flex w-full border-b border-sidebar-border/70">
                    <div className="mx-auto flex h-12 w-full items-center justify-start px-4 text-neutral-500 md:max-w-7xl">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                </div>
            )}

            {/* Global Search Dialog */}
            <GlobalSearch open={searchOpen} onOpenChange={setSearchOpen} />
        </>
    );
}
