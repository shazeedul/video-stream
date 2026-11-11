import { AppShell } from '@/components/app-shell';
import { Content } from '@/components/portal/content';
import { Header } from '@/components/portal/header';
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
