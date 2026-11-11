import { Sidebar } from '@/components/admin/sidebar';
import { SidebarHeader } from '@/components/admin/sidebar-header';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren } from 'react';

export default function SidebarLayout({ children, breadcrumbs = [] }: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    return (
        <SidebarProvider>
            <Sidebar />
            <SidebarInset>
                <SidebarHeader breadcrumbs={breadcrumbs} />
                <main className="flex flex-1 flex-col gap-4 overflow-y-auto">{children}</main>
            </SidebarInset>
        </SidebarProvider>
    );
}
