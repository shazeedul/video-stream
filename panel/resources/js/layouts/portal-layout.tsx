import { FlashMessage } from '@/components/flash-message';
import { Toaster } from '@/components/ui/sonner';
import { type BreadcrumbItem } from '@/types';
import { type ReactNode } from 'react';
import HeaderLayout from './portal/header-layout';

interface PortalLayoutProps {
    readonly children: ReactNode;
    readonly breadcrumbs?: BreadcrumbItem[];
}

export default function PortalLayout({ children, breadcrumbs, ...props }: Readonly<PortalLayoutProps>) {
    return (
        <HeaderLayout breadcrumbs={breadcrumbs} {...props}>
            {children}
            <FlashMessage />
            <Toaster />
        </HeaderLayout>
    );
}
