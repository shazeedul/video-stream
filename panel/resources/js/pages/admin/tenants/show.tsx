import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Head } from '@inertiajs/react';
import React from 'react';

interface TenantDetail {
    id: string;
    name: string;
    slug: string;
    status: string;
    users_count?: number;
    created_at?: string;
    memberships?: Array<{
        id: string;
        role: string;
        user?: {
            id: number;
            name: string;
            email: string;
        };
    }>;
}

interface Props {
    tenant: TenantDetail;
}

export default function TenantShow({ tenant }: Props) {
    const { __ } = useLocalizer();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('Tenants'),
            href: '/panel/admin/tenants',
        },
        {
            title: tenant.name,
            href: `/panel/admin/tenants/${tenant.id}`,
        },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={tenant.name} />
            <div className="space-y-6">
                <div>
                    <h2 className="text-2xl font-bold tracking-tight">{tenant.name}</h2>
                    <p className="text-muted-foreground">
                        {__('Tenant Details')}
                    </p>
                </div>
            </div>
        </AdminLayout>
    );
}
