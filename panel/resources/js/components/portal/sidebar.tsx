import { NavFooter } from '@/components/nav-footer';
import { Nav } from '@/components/portal/nav';
import {
    Sidebar as DefaultSidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useUserMainNavigation } from '@/config/usePortalNavigation';
import { dashboard } from '@/routes/portal';
import { Link } from '@inertiajs/react';
import AppLogo from '../app-logo';
import { NavMainEnhanced } from '../nav-main-enhanced';

export function Sidebar() {
    const { navGroups, footerNavItems } = useUserMainNavigation();

    return (
        <DefaultSidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard().url} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup>
                    <SidebarGroupContent></SidebarGroupContent>
                </SidebarGroup>
                <NavMainEnhanced groups={navGroups} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <Nav />
            </SidebarFooter>
        </DefaultSidebar>
    );
}
