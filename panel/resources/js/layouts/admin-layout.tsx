import { FlashMessage } from '@/components/flash-message';
import { Toaster } from '@/components/ui/sonner';
import SidebarLayout from '@/layouts/admin/sidebar-layout';
import { type BreadcrumbItem } from '@/types';
import { type ReactNode } from 'react';

interface AdminLayoutProps {
    readonly children: ReactNode;
    readonly breadcrumbs?: BreadcrumbItem[];
}

export default function AdminLayout({ children, breadcrumbs, ...props }: Readonly<AdminLayoutProps>) {
    return (
        <SidebarLayout breadcrumbs={breadcrumbs} {...props}>
            {children}
            <FlashMessage />
            <Toaster />
        </SidebarLayout>
    );
}

export { useAdminMainNavigation } from '@/config/useAdminNavigation';

