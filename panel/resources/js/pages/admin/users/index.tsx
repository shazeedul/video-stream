import DeleteConfirmationDialog from '@/components/delete-confirmation-dialog';
import FilterPanel from '@/components/filter-panel';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { UserStatus } from '@/enums';
import AdminLayout from '@/layouts/admin-layout';
import UserSheet from '@/pages/admin/users/partials/create-edit-sheet';
import { dashboard } from '@/routes/admin';
import {
    destroy as usersDestroy,
    index as usersIndex,
    show as usersShow,
    unsuspend as usersUnsuspend,
    verifyEmail as usersVerifyEmail,
} from '@/routes/admin/users';
import { type BreadcrumbItem, type PaginatedData, type Role, type User } from '@/types';
import { type FilterConfig } from '@/types/filters';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Can } from '@devwizard/laravel-react-permissions';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { Edit, Eye, Mail, MoreHorizontal, Plus, ShieldCheck, ShieldX, Trash2, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import UserShowSheet from './partials/show-sheet';
import SuspendUserSheet from './partials/suspend-sheet';

interface Props {
    users: PaginatedData<User>;
    roles: Role[];
    filters: {
        search: string;
        status: string;
        role: string;
        sort: string;
        per_page: number;
    };
    user?: User & {
        roles: Array<{
            id: number;
            name: string;
            permissions: Array<{
                id: number;
                name: string;
            }>;
        }>;
    };
}

export default function Index({ users, roles, filters, user: propUser }: Props) {
    const { __ } = useLocalizer();
    const [sheetOpen, setSheetOpen] = useState(false);
    const [selectedUser, setSelectedUser] = useState<User | null>(null);
    const [sheetMode, setSheetMode] = useState<'create' | 'edit'>('create');
    const [showSheetOpen, setShowSheetOpen] = useState(false);
    const [deleteUser, setDeleteUser] = useState<User | null>(null);
    const [deleteLoading, setDeleteLoading] = useState(false);
    const [suspendSheetOpen, setSuspendSheetOpen] = useState(false);
    const [userToSuspend, setUserToSuspend] = useState<User | null>(null);
    const [viewUser, setViewUser] = useState<
        | (User & {
              roles: Array<{
                  id: number;
                  name: string;
                  permissions: Array<{
                      id: number;
                      name: string;
                  }>;
              }>;
          })
        | null
    >(null);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('System'),
            href: dashboard().url,
        },
        {
            title: __('Users'),
            href: usersIndex().url,
        },
    ];

    // If a user prop is passed, it means we're coming from the details route
    // so we should open the show sheet immediately
    useEffect(() => {
        if (propUser) {
            setViewUser(propUser);
            setShowSheetOpen(true);
        }
    }, [propUser]);

    const handleCreate = () => {
        setSelectedUser(null);
        setSheetMode('create');
        setSheetOpen(true);
    };

    const handleEdit = (user: User) => {
        setSelectedUser(user);
        setSheetMode('edit');
        setSheetOpen(true);
    };

    const handleView = async (user: User) => {
        await axios
            .get(usersShow({ user: user.id }).url)
            .then((response) => {
                const data = response.data.user as User & {
                    roles: Array<{
                        id: number;
                        name: string;
                        permissions: Array<{
                            id: number;
                            name: string;
                        }>;
                    }>;
                };
                setViewUser(data);
                setShowSheetOpen(true);
            })
            .catch((error) => {
                console.error('Error fetching user details:', error);
                // You might want to show an error toast/notification here
            });
    };

    const handleDelete = (user: User) => {
        setDeleteUser(user);
    };

    const confirmDelete = () => {
        if (!deleteUser) return;

        setDeleteLoading(true);
        router.delete(usersDestroy({ user: deleteUser.id }).url, {
            onSuccess: () => {
                setDeleteUser(null);
                setDeleteLoading(false);
            },
            onError: () => {
                setDeleteLoading(false);
            },
            preserveScroll: true,
        });
    };

    const handleSuspend = (user: User) => {
        setUserToSuspend(user);
        setSuspendSheetOpen(true);
    };

    const handleUnsuspend = (user: User) => {
        router.post(
            usersUnsuspend({ user: user.id }).url,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const handleVerifyEmail = (user: User) => {
        router.post(
            usersVerifyEmail({ user: user.id }).url,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    // Build filter configuration dynamically with safety check
    const roleOptions = [{ value: 'all', label: __('All Roles') }, ...(roles || []).map((role) => ({ value: role.name, label: role.display_name }))];

    const filterConfig: FilterConfig = {
        fields: [
            {
                key: 'search',
                type: 'search',
                label: __('Search Users'),
                placeholder: __('Search by name, email...'),
                defaultValue: '',
            },
            {
                key: 'status',
                type: 'select',
                label: __('Status'),
                placeholder: __('Filter by status'),
                defaultValue: 'all',
                options: [
                    { value: 'all', label: __('All Users') },
                    { value: 'verified', label: __('Verified') },
                    { value: 'unverified', label: __('Unverified') },
                ],
            },
            {
                key: 'role',
                type: 'select',
                label: __('Role'),
                placeholder: __('Filter by role'),
                defaultValue: 'all',
                options: roleOptions,
            },
            {
                key: 'sort',
                type: 'select',
                label: __('Sort By'),
                placeholder: __('Sort by'),
                defaultValue: 'name',
                options: [
                    { value: 'name', label: __('Name (A-Z)') },
                    { value: 'email', label: __('Email (A-Z)') },
                    { value: 'created_at', label: __('Newest First') },
                    { value: 'roles_count', label: __('Most Roles') },
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
            role: 'all',
            sort: 'name',
            per_page: 15,
        },
        baseUrl: usersIndex().url,
        preserveScroll: true,
        preserveState: true,
        only: ['users'],
        debounceMs: 300,
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Users')} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                            <Users className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold">{__('Users')}</h1>
                            <p className="text-sm text-muted-foreground">{__('Manage user accounts and permissions')}</p>
                        </div>
                    </div>
                    <Can permission="users.create">
                        <Button onClick={handleCreate}>
                            <Plus className="mr-2 h-4 w-4" />
                            {__('Add User')}
                        </Button>
                    </Can>
                </div>

                {/* Filters */}
                <FilterPanel config={filterConfig} initialFilters={filters} />

                {/* Users Table */}
                {users.data.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-8 text-center">
                        <Users className="mx-auto h-12 w-12 text-muted-foreground/50" />
                        <h3 className="mt-4 text-lg font-medium">{__('No users found')}</h3>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {filters.search || filters.status || filters.role
                                ? __('Try adjusting your search criteria or filters.')
                                : __('Get started by creating your first user.')}
                        </p>
                        {!filters.search && !filters.status && !filters.role && (
                            <Can permission="users.create">
                                <Button onClick={handleCreate} className="mt-4">
                                    <Plus className="mr-2 h-4 w-4" />
                                    {__('Add User')}
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
                                    <th className="px-4 py-3 text-left text-sm font-medium">{__('Email')}</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">{__('Roles')}</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">{__('Status')}</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">{__('Created')}</th>
                                    <th className="px-4 py-3 text-right text-sm font-medium">{__('Actions')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {users.data.map((user) => (
                                    <tr key={user.id} className="hover:bg-muted/25">
                                        <td className="px-4 py-3">
                                            <div className="font-medium">{user.name}</div>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-muted-foreground">{user.email}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                {user.roles && Array.isArray(user.roles) && user.roles.length > 0 ? (
                                                    user.roles.map((role: Role) => (
                                                        <Badge key={role.id} variant="outline">
                                                            {role.display_name}
                                                        </Badge>
                                                    ))
                                                ) : (
                                                    <Badge variant="secondary">{__('No roles')}</Badge>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-col gap-1">
                                                {user.status === UserStatus.SUSPENDED ? (
                                                    <Badge variant="destructive">{__('Suspended')}</Badge>
                                                ) : user.status === UserStatus.INACTIVE ? (
                                                    <Badge variant="secondary">{__('Inactive')}</Badge>
                                                ) : (
                                                    <Badge variant="default">{__('Active')}</Badge>
                                                )}
                                                {!user.email_verified_at && (
                                                    <Badge variant="outline" className="text-xs">
                                                        {__('Email unverified')}
                                                    </Badge>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-muted-foreground">
                                            {user.created_at ? new Date(user.created_at).toLocaleDateString() : '-'}
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
                                                        <Can permission="users.view">
                                                            <DropdownMenuItem onClick={() => handleView(user)} className="cursor-pointer">
                                                                <Eye className="mr-2 h-4 w-4" />
                                                                {__('View')}
                                                            </DropdownMenuItem>
                                                        </Can>
                                                        <Can permission="users.update">
                                                            <DropdownMenuItem onClick={() => handleEdit(user)} className="cursor-pointer">
                                                                <Edit className="mr-2 h-4 w-4" />
                                                                {__('Edit')}
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            {!user.email_verified_at && (
                                                                <DropdownMenuItem onClick={() => handleVerifyEmail(user)} className="cursor-pointer">
                                                                    <Mail className="mr-2 h-4 w-4" />
                                                                    {__('Verify Email')}
                                                                </DropdownMenuItem>
                                                            )}
                                                            {user.status === UserStatus.SUSPENDED ? (
                                                                <DropdownMenuItem
                                                                    onClick={() => handleUnsuspend(user)}
                                                                    className="cursor-pointer text-green-600 focus:text-green-600"
                                                                >
                                                                    <ShieldCheck className="mr-2 h-4 w-4" />
                                                                    {__('Unsuspend User')}
                                                                </DropdownMenuItem>
                                                            ) : (
                                                                <DropdownMenuItem
                                                                    onClick={() => handleSuspend(user)}
                                                                    className="cursor-pointer text-orange-600 focus:text-orange-600"
                                                                >
                                                                    <ShieldX className="mr-2 h-4 w-4" />
                                                                    {__('Suspend User')}
                                                                </DropdownMenuItem>
                                                            )}
                                                        </Can>
                                                        <Can permission="users.delete">
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                onClick={() => handleDelete(user)}
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
                <Pagination data={users} />

                {/* User Sheet */}
                <UserSheet open={sheetOpen} onOpenChange={setSheetOpen} user={selectedUser} roles={roles} mode={sheetMode} />

                {/* User Show Sheet */}
                <UserShowSheet user={viewUser} open={showSheetOpen} onClose={() => setShowSheetOpen(false)} />

                {/* Suspend User Sheet */}
                <SuspendUserSheet
                    user={userToSuspend}
                    open={suspendSheetOpen}
                    onClose={() => {
                        setSuspendSheetOpen(false);
                        setUserToSuspend(null);
                    }}
                />

                {/* Delete Confirmation Dialog */}
                <DeleteConfirmationDialog
                    open={!!deleteUser}
                    onOpenChange={() => setDeleteUser(null)}
                    title={__('Delete User')}
                    description={__('Are you sure you want to delete this user?')}
                    itemName={deleteUser?.name}
                    onConfirm={confirmDelete}
                    loading={deleteLoading}
                />
            </div>
        </AdminLayout>
    );
}
