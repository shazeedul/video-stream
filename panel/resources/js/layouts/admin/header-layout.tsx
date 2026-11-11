import { Content } from '@/components/admin/content';
import { Header } from '@/components/admin/header';
import { AppShell } from '@/components/app-shell';
import { type BreadcrumbItem } from '@/types';
import type { PropsWithChildren } from 'react';

export default function HeaderLayout({ children, breadcrumbs }: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    return (
        <AppShell>
            <Header breadcrumbs={breadcrumbs} />
            <Content>{children}</Content>
        </AppShell>
    );
}
