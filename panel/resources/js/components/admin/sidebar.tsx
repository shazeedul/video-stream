import { Nav } from '@/components/admin/nav';
import { NavFooter } from '@/components/nav-footer';
import {
    Sidebar as DefaultSidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useAdminMainNavigation } from '@/config/useAdminNavigation';
import { dashboard } from '@/routes/admin';
import { Link } from '@inertiajs/react';
import AppLogo from '../app-logo';
import { NavMainEnhanced } from '../nav-main-enhanced';

export function Sidebar() {
    const { navGroups, footerNavItems } = useAdminMainNavigation();

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
                <NavMainEnhanced groups={navGroups} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <Nav />
            </SidebarFooter>
        </DefaultSidebar>
    );
}
