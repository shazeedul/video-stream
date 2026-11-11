import DeleteConfirmationDialog from '@/components/delete-confirmation-dialog';
import FilterPanel from '@/components/filter-panel';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import AdminLayout from '@/layouts/admin-layout';
import { dashboard } from '@/routes/admin';
import { create as rolesCreate, destroy as rolesDestroy, edit as rolesEdit, index as rolesIndex, show as rolesShow } from '@/routes/admin/roles';
import { type BreadcrumbItem, type PaginatedData, type Role } from '@/types';
import { type FilterConfig } from '@/types/filters';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Can } from '@devwizard/laravel-react-permissions';
import { Head, Link, useForm } from '@inertiajs/react';
import { Edit, Eye, MoreHorizontal, Plus, Shield, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Props {
    roles: PaginatedData<Role>;
    filters: {
        search: string;
        sort: string;
        per_page: number;
        is_default: string | null;
    };
}

export default function Index({ roles, filters }: Props) {
    const { __ } = useLocalizer();
    const [deleteRole, setDeleteRole] = useState<Role | null>(null);
    const { delete: destroy, processing } = useForm();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('System'),
            href: dashboard().url,
        },
        {
            title: __('Roles'),
            href: rolesIndex().url,
        },
    ];

    const filterConfig: FilterConfig = {
        fields: [
            {
                key: 'search',
                type: 'search',
                label: __('Search Roles'),
                placeholder: __('Search by name...'),
                defaultValue: '',
            },
            {
                key: 'is_default',
                type: 'select',
                label: __('Status'),
                placeholder: __('Filter by status'),
                defaultValue: '-1',
                options: [
                    { value: '-1', label: __('All Roles') },
                    { value: '1', label: __('Default Roles') },
                    { value: '0', label: __('Custom Roles') },
                ],
            },
            {
                key: 'sort',
                type: 'select',
                label: __('Sort By'),
                placeholder: __('Sort by'),
                defaultValue: 'name',
                options: [
                    { value: 'name', label: __('Name (A-Z)') },
                    { value: 'created_at', label: __('Newest First') },
                    { value: 'permissions_count', label: __('Most Permissions') },
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
            is_default: '-1',
            sort: 'name',
            per_page: 15,
        },
        baseUrl: rolesIndex().url,
        preserveScroll: true,
        preserveState: true,
        only: ['roles'],
        debounceMs: 300,
    };

    const handleDelete = (role: Role) => {
        setDeleteRole(role);
    };

    const confirmDelete = () => {
        if (!deleteRole) return;

        destroy(rolesDestroy({ role: deleteRole.id }).url, {
            onSuccess: () => setDeleteRole(null),
        });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Role Management')} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">{__('Role Management')}</h1>
                        <p className="text-muted-foreground">{__('Manage system roles and their permissions')}</p>
                    </div>

                    <Can permission="roles.create">
                        <Button asChild>
                            <Link href={rolesCreate().url}>
                                <Plus className="mr-2 h-4 w-4" />
                                {__('Create Role')}
                            </Link>
                        </Button>
                    </Can>
                </div>

                {/* Filter Panel */}
                <FilterPanel initialFilters={filters} config={filterConfig} className="my-6" />

                {/* Roles Table */}
                {roles.data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-12 text-center">
                        <Shield className="mx-auto h-12 w-12 text-muted-foreground" />
                        <h3 className="mt-4 text-lg font-medium">{__('No roles found')}</h3>
                        <p className="mt-2 text-muted-foreground">
                            {filters.search ? __('No roles match your search criteria.') : __('Get started by creating your first role.')}
                        </p>
                        {!filters.search && (
                            <Can permission="roles.create">
                                <Button asChild className="mt-4">
                                    <Link href={rolesCreate().url}>
                                        <Plus className="mr-2 h-4 w-4" />
                                        {__('Create Role')}
                                    </Link>
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
                                    <th className="px-4 py-3 text-left text-sm font-medium">{__('Description')}</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">{__('Guard')}</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">{__('Permissions')}</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">{__('Status')}</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">{__('Created')}</th>
                                    <th className="px-4 py-3 text-right text-sm font-medium">{__('Actions')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {roles.data.map((role) => (
                                    <tr key={role.id} className="hover:bg-muted/25">
                                        <td className="px-4 py-3">
                                            <div>
                                                <div className="font-medium">{role.display_name}</div>
                                                <div className="text-xs text-muted-foreground">{role.name}</div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="max-w-xs truncate text-sm text-muted-foreground">{role.description || '-'}</div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant="outline">{role.guard_name}</Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="text-sm text-muted-foreground">
                                                {role.permissions?.length || 0} {__('permissions')}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            {role.is_default ? <Badge>{__('Default')}</Badge> : <Badge variant="secondary">{__('Custom')}</Badge>}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-muted-foreground">{new Date(role.created_at).toLocaleDateString()}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button size="sm" variant="ghost" className="cursor-pointer">
                                                            <MoreHorizontal className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <Can permission="roles.view">
                                                            <DropdownMenuItem asChild className="cursor-pointer">
                                                                <Link href={rolesShow({ role: role.id }).url}>
                                                                    <Eye className="mr-2 h-4 w-4" />
                                                                    {__('View')}
                                                                </Link>
                                                            </DropdownMenuItem>
                                                        </Can>

                                                        {!role.is_default && (
                                                            <>
                                                                <Can permission="roles.update">
                                                                    <DropdownMenuItem asChild className="cursor-pointer">
                                                                        <Link href={rolesEdit({ role: role.id }).url}>
                                                                            <Edit className="mr-2 h-4 w-4" />
                                                                            {__('Edit')}
                                                                        </Link>
                                                                    </DropdownMenuItem>
                                                                </Can>

                                                                <Can permission="roles.delete">
                                                                    <DropdownMenuSeparator />

                                                                    <DropdownMenuItem
                                                                        onClick={() => handleDelete(role)}
                                                                        className="cursor-pointer text-destructive focus:text-destructive"
                                                                    >
                                                                        <Trash2 className="mr-2 h-4 w-4" />
                                                                        {__('Delete')}
                                                                    </DropdownMenuItem>
                                                                </Can>
                                                            </>
                                                        )}
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
                <Pagination data={roles} />
            </div>

            {/* Delete Confirmation Dialog */}
            <DeleteConfirmationDialog
                open={!!deleteRole}
                onOpenChange={() => setDeleteRole(null)}
                title={__('Delete Role')}
                description={__('Are you sure you want to delete this role?')}
                itemName={deleteRole?.name}
                onConfirm={confirmDelete}
                loading={processing}
            />
        </AdminLayout>
    );
}
