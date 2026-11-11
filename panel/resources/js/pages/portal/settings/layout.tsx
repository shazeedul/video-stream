import { UserSettingsHeader } from '@/components/shared/settings-header';
import TabNavigation from '@/components/tab-navigation';
import { useSettingsNavigation } from '@/config/usePortalNavigation';
import PortalLayout from '@/layouts/portal-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { type PropsWithChildren, type ReactNode } from 'react';

interface SettingsLayoutProps {
    readonly breadcrumbs?: BreadcrumbItem[];
    readonly header?: ReactNode;
}

export default function SettingsLayout({
    children,
    breadcrumbs,
    header,
}: Readonly<PropsWithChildren<SettingsLayoutProps>>) {
    const { auth } = usePage<SharedData>().props;
    const settingsNavigation = useSettingsNavigation();

    return (
        <PortalLayout breadcrumbs={breadcrumbs}>
            <TabNavigation
                header={header ?? <UserSettingsHeader user={auth.user} />}
                navigation={settingsNavigation}
            >
                {children}
            </TabNavigation>
        </PortalLayout>
    );
}

export { useSettingsNavigation };
