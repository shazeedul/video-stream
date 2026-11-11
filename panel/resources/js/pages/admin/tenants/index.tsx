import DeleteConfirmationDialog from '@/components/delete-confirmation-dialog';
import FilterPanel from '@/components/filter-panel';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { TenantStatus } from '@/enums';
import AdminLayout from '@/layouts/admin-layout';
import CreateEditSheet from '@/pages/admin/tenants/partials/create-edit-sheet';
import SuspendTenantSheet from '@/pages/admin/tenants/partials/suspend-sheet';
import { dashboard } from '@/routes/admin';
import {
    destroy as tenantsDestroy,
    index as tenantsIndex,
    show as tenantsShow,
    status as tenantsStatus,
} from '@/routes/admin/tenants';
import { type BreadcrumbItem, type PaginatedData, type Tenant } from '@/types';
import { type FilterConfig } from '@/types/filters';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Can } from '@devwizard/laravel-react-permissions';
import { Head, Link, router } from '@inertiajs/react';
import { Building2, Edit, Eye, MoreHorizontal, Plus, ShieldCheck, ShieldX, Trash2, Users } from 'lucide-react';
import React, { useState } from 'react';

interface Props {
    tenants: PaginatedData<Tenant>;
    filters: {
        search: string | null;
        status: string | null;
        per_page?: number;
    };
}

export default function Index({ tenants, filters }: Props) {
    const { __ } = useLocalizer();
    const [sheetOpen, setSheetOpen] = useState(false);
    const [selectedTenant, setSelectedTenant] = useState<Tenant | null>(null);
    const [sheetMode, setSheetMode] = useState<'create' | 'edit'>('create');

    const [deleteTenant, setDeleteTenant] = useState<Tenant | null>(null);
    const [deleteLoading, setDeleteLoading] = useState(false);

    const [suspendSheetOpen, setSuspendSheetOpen] = useState(false);
    const [tenantToSuspend, setTenantToSuspend] = useState<Tenant | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('System'),
            href: dashboard().url,
        },
        {
            title: __('Tenants'),
            href: tenantsIndex().url,
        },
    ];

    const handleCreate = () => {
        setSelectedTenant(null);
        setSheetMode('create');
        setSheetOpen(true);
    };

    const handleEdit = (tenant: Tenant) => {
        setSelectedTenant(tenant);
        setSheetMode('edit');
        setSheetOpen(true);
    };

    const handleDelete = (tenant: Tenant) => {
        setDeleteTenant(tenant);
    };

    const confirmDelete = () => {
        if (!deleteTenant) return;

        setDeleteLoading(true);
        router.delete(tenantsDestroy({ tenant: deleteTenant.id }).url, {
            onSuccess: () => {
                setDeleteTenant(null);
                setDeleteLoading(false);
            },
            onError: () => {
                setDeleteLoading(false);
            },
            preserveScroll: true,
        });
    };

    const handleSuspend = (tenant: Tenant) => {
        setTenantToSuspend(tenant);
        setSuspendSheetOpen(true);
    };

    const handleUnsuspend = (tenant: Tenant) => {
        router.patch(
            tenantsStatus({ tenant: tenant.id }).url,
            { status: TenantStatus.ACTIVE },
            { preserveScroll: true },
        );
    };

    const filterConfig: FilterConfig = {
        fields: [
            {
                key: 'search',
                type: 'search',
                label: __('Search Tenants'),
                placeholder: __('Search by name, slug...'),
                defaultValue: '',
            },
            {
                key: 'status',
                type: 'select',
                label: __('Status'),
                placeholder: __('Filter by status'),
                defaultValue: 'all',
                options: [
                    { value: 'all', label: __('All Statuses') },
                    { value: TenantStatus.ACTIVE, label: __('Active') },
                    { value: TenantStatus.SUSPENDED, label: __('Suspended') },
                    { value: TenantStatus.ARCHIVED, label: __('Archived') },
                ],
            },
            {
                key: 'per_page',
                type: 'select',
                label: __('Per Page'),
                placeholder: __('Items per page'),
                defaultValue: 15,
                options: [
                    { value: 10, label: __('10 per page') },
                    { value: 15, label: __('15 per page') },
                    { value: 25, label: __('25 per page') },
                    { value: 50, label: __('50 per page') },
                ],
            },
        ],
        defaultFilters: {
            search: '',
            status: 'all',
            per_page: 15,
        },
        baseUrl: tenantsIndex().url,
        preserveScroll: true,
        preserveState: true,
        only: ['tenants'],
        debounceMs: 300,
    };

    const initialFilters = {
        search: filters.search ?? '',
        status: filters.status || 'all',
        per_page: filters.per_page ?? 15,
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case TenantStatus.ACTIVE:
                return <Badge variant="default">{__('Active')}</Badge>;
            case TenantStatus.SUSPENDED:
                return <Badge variant="destructive">{__('Suspended')}</Badge>;
            case TenantStatus.ARCHIVED:
                return <Badge variant="secondary">{__('Archived')}</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Tenants')} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                            <Building2 className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold">{__('Tenants')}</h1>
                            <p className="text-sm text-muted-foreground">{__('Manage platform organizations and tenants')}</p>
                        </div>
                    </div>
                    <Can permission="tenants.create">
                        <Button onClick={handleCreate}>
                            <Plus className="mr-2 h-4 w-4" />
                            {__('Add Tenant')}
                        </Button>
                    </Can>
                </div>

                {/* Filters */}
                <FilterPanel config={filterConfig} initialFilters={initialFilters} />

                {/* Tenants Table */}
                {tenants.data.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-8 text-center">
                        <Building2 className="mx-auto h-12 w-12 text-muted-foreground/50" />
                        <h3 className="mt-4 text-lg font-medium">{__('No tenants found')}</h3>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {filters.search || (filters.status && filters.status !== 'all')
                                ? __('Try adjusting your search criteria or filters.')
                                : __('Get started by creating your first tenant.')}
                        </p>
                        {!filters.search && (!filters.status || filters.status === 'all') && (
                            <Can permission="tenants.create">
                                <Button onClick={handleCreate} className="mt-4">
                                    <Plus className="mr-2 h-4 w-4" />
                                    {__('Add Tenant')}
                                </Button>
                            </Can>
                        )}
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-md border">
                        <table className="w-full">
                            <thead className="bg-muted/50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-sm font-medium">{__('Name')}</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">{__('Slug')}</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">{__('Members')}</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">{__('Status')}</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">{__('Created')}</th>
                                    <th className="px-4 py-3 text-right text-sm font-medium">{__('Actions')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {tenants.data.map((tenant) => (
                                    <tr key={tenant.id} className="hover:bg-muted/25">
                                        <td className="px-4 py-3">
                                            <div className="font-medium text-foreground">{tenant.name}</div>
                                        </td>
                                        <td className="px-4 py-3 text-sm font-mono text-muted-foreground">
                                            {tenant.slug}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-1.5 text-sm">
                                                <Users className="h-4 w-4 text-muted-foreground" />
                                                <span>{tenant.users_count ?? 0}</span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {getStatusBadge(tenant.status)}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-muted-foreground">
                                            {tenant.created_at ? new Date(tenant.created_at).toLocaleDateString() : '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button size="sm" variant="ghost" className="cursor-pointer">
                                                            <MoreHorizontal className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <Can permission="tenants.view">
                                                            <DropdownMenuItem asChild className="cursor-pointer">
                                                                <Link href={tenantsShow({ tenant: tenant.id }).url}>
                                                                    <Eye className="mr-2 h-4 w-4" />
                                                                    {__('View')}
                                                                </Link>
                                                            </DropdownMenuItem>
                                                        </Can>

                                                        <Can permission="tenants.update">
                                                            <DropdownMenuItem onClick={() => handleEdit(tenant)} className="cursor-pointer">
                                                                <Edit className="mr-2 h-4 w-4" />
                                                                {__('Edit')}
                                                            </DropdownMenuItem>
                                                        </Can>

                                                        <Can permission="tenants.suspend">
                                                            <DropdownMenuSeparator />
                                                            {tenant.status === TenantStatus.SUSPENDED ? (
                                                                <DropdownMenuItem
                                                                    onClick={() => handleUnsuspend(tenant)}
                                                                    className="cursor-pointer text-green-600 focus:text-green-600"
                                                                >
                                                                    <ShieldCheck className="mr-2 h-4 w-4" />
                                                                    {__('Unsuspend Tenant')}
                                                                </DropdownMenuItem>
                                                            ) : (
                                                                <DropdownMenuItem
                                                                    onClick={() => handleSuspend(tenant)}
                                                                    className="cursor-pointer text-orange-600 focus:text-orange-600"
                                                                >
                                                                    <ShieldX className="mr-2 h-4 w-4" />
                                                                    {__('Suspend Tenant')}
                                                                </DropdownMenuItem>
                                                            )}
                                                        </Can>

                                                        <Can permission="tenants.delete">
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                onClick={() => handleDelete(tenant)}
                                                                className="cursor-pointer text-destructive focus:text-destructive"
                                                            >
                                                                <Trash2 className="mr-2 h-4 w-4" />
                                                                {__('Delete')}
                                                            </DropdownMenuItem>
                                                        </Can>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Pagination */}
                <Pagination data={tenants} />

                {/* Tenant Create/Edit Sheet */}
                <CreateEditSheet
                    open={sheetOpen}
                    onOpenChange={setSheetOpen}
                    tenant={selectedTenant}
                    mode={sheetMode}
                />

                {/* Suspend Tenant Sheet */}
                <SuspendTenantSheet
                    tenant={tenantToSuspend}
                    open={suspendSheetOpen}
                    onClose={() => {
                        setSuspendSheetOpen(false);
                        setTenantToSuspend(null);
                    }}
                />

                {/* Delete Confirmation Dialog */}
                <DeleteConfirmationDialog
                    open={!!deleteTenant}
                    onOpenChange={() => setDeleteTenant(null)}
                    title={__('Delete Tenant')}
                    description={__('Are you sure you want to delete this tenant organization? This action cannot be undone.')}
                    itemName={deleteTenant?.name}
                    onConfirm={confirmDelete}
                    loading={deleteLoading}
                />
            </div>
        </AdminLayout>
    );
}
