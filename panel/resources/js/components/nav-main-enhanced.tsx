import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { type NavGroup, type NavItem } from '@/types';
import { getCurrentUrl, isActiveUrl } from '@/utils/navigation';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Can, usePermissions } from '@devwizard/laravel-react-permissions';
import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useCallback, useMemo } from 'react';

interface NavMainProps {
    items?: NavItem[];
    groups?: NavGroup[];
}

export function NavMainEnhanced({ items = [], groups = [] }: NavMainProps) {
    const currentUrl = getCurrentUrl();
    const { hasPermission } = usePermissions();
    const { __ } = useLocalizer();

    const isActive = useCallback(
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

    const hasActiveChild = useCallback(
        (item: NavItem): boolean => {
            return item.items?.some((child: NavItem) => isActive(child)) || false;
        },
        [isActive],
    );

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

    const permissionCache = useMemo(() => {
        const cache = new Map<string, boolean>();

        const calculateItemPermissions = (items: NavItem[]) => {
            items.forEach((item) => {
                const permission = getParentPermission(item);
                const cacheKey = permission || '*';

                if (!cache.has(cacheKey)) {
                    cache.set(cacheKey, !permission || permission === '*' ? true : hasPermission(permission));
                }

                if (item.items) {
                    calculateItemPermissions(item.items);
                }
            });
        };

        calculateItemPermissions(items);
        groups.forEach((group) => calculateItemPermissions(group.items));

        return cache;
    }, [items, groups, hasPermission, getParentPermission]);

    const isItemAccessible = useCallback(
        (item: NavItem): boolean => {
            const permission = getParentPermission(item);
            const cacheKey = permission || '*';
            return permissionCache.get(cacheKey) ?? true;
        },
        [getParentPermission, permissionCache],
    );

    const isGroupAccessible = useCallback(
        (group: NavGroup): boolean => {
            return group.items.some(isItemAccessible);
        },
        [isItemAccessible],
    );

    const { filteredItems, filteredGroups } = useMemo(() => {
        return {
            filteredItems: items.filter(isItemAccessible),
            filteredGroups: groups.filter(isGroupAccessible).map((group) => ({
                ...group,
                items: group.items.filter(isItemAccessible),
            })),
        };
    }, [items, groups, isItemAccessible, isGroupAccessible]);

    const renderNavItem = useCallback(
        (item: NavItem, isSubItem = false) => {
            const hasSubItems = item.items && item.items.length > 0;
            const ItemComponent = isSubItem ? SidebarMenuSubItem : SidebarMenuItem;
            const ButtonComponent = isSubItem ? SidebarMenuSubButton : SidebarMenuButton;
            const effectivePermission = getParentPermission(item);

            if (hasSubItems) {
                // Use optimized hasActiveChild check
                const childIsActive = hasActiveChild(item);
                return (
                    <Can key={item.title} permission={effectivePermission ?? '*'}>
                        <Collapsible asChild defaultOpen={childIsActive}>
                            <ItemComponent>
                                <CollapsibleTrigger asChild>
                                    <ButtonComponent isActive={isActive(item)} tooltip={{ children: item.title }} className="w-full cursor-pointer">
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                        <ChevronRight className="ml-auto h-4 w-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                    </ButtonComponent>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <SidebarMenuSub>
                                        {item.items?.filter(isItemAccessible).map((subItem) => renderNavItem(subItem, true))}
                                    </SidebarMenuSub>
                                </CollapsibleContent>
                            </ItemComponent>
                        </Collapsible>
                    </Can>
                );
            }

            return (
                <Can key={item.title} permission={item.permission ?? '*'}>
                    <ItemComponent>
                        <ButtonComponent asChild isActive={isActive(item)} tooltip={{ children: item.title }}>
                            <Link href={item.href} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </ButtonComponent>
                    </ItemComponent>
                </Can>
            );
        },
        [isActive, getParentPermission, isItemAccessible, hasActiveChild],
    );

    return (
        <>
            {filteredItems.length > 0 && (
                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel>{__('Platform')}</SidebarGroupLabel>
                    <SidebarMenu>{filteredItems.map((item) => renderNavItem(item))}</SidebarMenu>
                </SidebarGroup>
            )}

            {filteredGroups.map((group) => (
                <SidebarGroup key={group.title} className="px-2 py-0">
                    <SidebarGroupLabel>{group.title}</SidebarGroupLabel>
                    <SidebarMenu>{group.items.map((item) => renderNavItem(item))}</SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
