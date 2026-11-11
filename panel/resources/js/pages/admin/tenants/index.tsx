import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem, type PaginatedData } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Head } from '@inertiajs/react';
import React from 'react';

interface TenantItem {
    id: string;
    name: string;
    slug: string;
    status: string;
    users_count?: number;
    created_at?: string;
}

interface Props {
    tenants: PaginatedData<TenantItem>;
    filters: {
        search: string | null;
        status: string | null;
    };
}

export default function TenantsIndex({ tenants, filters }: Props) {
    const { __ } = useLocalizer();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('Tenants'),
            href: '/panel/admin/tenants',
        },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Tenants')} />
            <div className="space-y-6">
                <div>
                    <h2 className="text-2xl font-bold tracking-tight">{__('Tenants')}</h2>
                    <p className="text-muted-foreground">
                        {__('Manage platform organizations and tenants.')}
                    </p>
                </div>
            </div>
        </AdminLayout>
    );
}
