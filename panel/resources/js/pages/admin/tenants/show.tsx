import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { TenantRole, TenantStatus, TenantUserStatus } from '@/enums';
import AdminLayout from '@/layouts/admin-layout';
import CreateEditSheet from '@/pages/admin/tenants/partials/create-edit-sheet';
import SuspendTenantSheet from '@/pages/admin/tenants/partials/suspend-sheet';
import { dashboard } from '@/routes/admin';
import { index as tenantsIndex, status as tenantsStatus } from '@/routes/admin/tenants';
import { type BreadcrumbItem, type Tenant } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Can } from '@devwizard/laravel-react-permissions';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Building2, Calendar, Edit, Mail, Shield, ShieldAlert, ShieldCheck, Users } from 'lucide-react';
import React, { useMemo, useState } from 'react';

interface Props {
    tenant: Tenant;
}

export default function TenantShow({ tenant }: Props) {
    const { __ } = useLocalizer();
    const [editSheetOpen, setEditSheetOpen] = useState(false);
    const [suspendSheetOpen, setSuspendSheetOpen] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('System'),
            href: dashboard().url,
        },
        {
            title: __('Tenants'),
            href: tenantsIndex().url,
        },
        {
            title: tenant.name,
            href: '',
        },
    ];

    const members = useMemo(() => {
        if (tenant.memberships && tenant.memberships.length > 0) {
            return tenant.memberships.map((m) => ({
                id: m.id,
                user_id: m.user_id,
                name: m.user?.name ?? __('Unknown User'),
                email: m.user?.email ?? '-',
                role: m.role,
                status: m.status,
                joined_at: m.created_at,
            }));
        }

        if (tenant.users && tenant.users.length > 0) {
            return tenant.users.map((u) => ({
                id: u.pivot?.id ?? String(u.id),
                user_id: u.id,
                name: u.name,
                email: u.email,
                role: u.pivot?.role ?? TenantRole.MEMBER,
                status: u.pivot?.status ?? TenantUserStatus.ACTIVE,
                joined_at: undefined,
            }));
        }

        return [];
    }, [tenant, __]);

    const handleUnsuspend = () => {
        router.patch(
            tenantsStatus({ tenant: tenant.id }).url,
            { status: TenantStatus.ACTIVE },
            { preserveScroll: true },
        );
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

    const getRoleBadge = (role: string) => {
        switch (role) {
            case TenantRole.OWNER:
                return <Badge variant="default" className="capitalize">{__('Owner')}</Badge>;
            case TenantRole.ADMIN:
                return <Badge variant="secondary" className="capitalize">{__('Admin')}</Badge>;
            case TenantRole.MEMBER:
                return <Badge variant="outline" className="capitalize">{__('Member')}</Badge>;
            case TenantRole.VIEWER:
                return <Badge variant="outline" className="capitalize">{__('Viewer')}</Badge>;
            default:
                return <Badge variant="outline" className="capitalize">{role}</Badge>;
        }
    };

    const getMembershipStatusBadge = (status: string) => {
        switch (status) {
            case TenantUserStatus.ACTIVE:
                return <Badge variant="default">{__('Active')}</Badge>;
            case TenantUserStatus.INVITED:
                return <Badge variant="outline">{__('Invited')}</Badge>;
            case TenantUserStatus.SUSPENDED:
                return <Badge variant="destructive">{__('Suspended')}</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`${tenant.name} - ${__('Tenant Details')}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                {/* Header with Back button and Actions */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" size="icon" asChild>
                            <Link href={tenantsIndex().url}>
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                            <Building2 className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-bold tracking-tight">{tenant.name}</h1>
                                {getStatusBadge(tenant.status)}
                            </div>
                            <p className="text-sm font-mono text-muted-foreground">{tenant.slug}</p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Can permission="tenants.update">
                            <Button variant="outline" onClick={() => setEditSheetOpen(true)}>
                                <Edit className="mr-2 h-4 w-4" />
                                {__('Edit Tenant')}
                            </Button>
                        </Can>

                        <Can permission="tenants.suspend">
                            {tenant.status === TenantStatus.SUSPENDED ? (
                                <Button variant="outline" onClick={handleUnsuspend} className="text-green-600 hover:text-green-700">
                                    <ShieldCheck className="mr-2 h-4 w-4" />
                                    {__('Unsuspend')}
                                </Button>
                            ) : (
                                <Button variant="outline" onClick={() => setSuspendSheetOpen(true)} className="text-destructive hover:text-destructive">
                                    <ShieldAlert className="mr-2 h-4 w-4" />
                                    {__('Suspend')}
                                </Button>
                            )}
                        </Can>
                    </div>
                </div>

                {/* Overview Stats Cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">{__('Status')}</CardTitle>
                            <Building2 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="mt-1">{getStatusBadge(tenant.status)}</div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                {tenant.status === TenantStatus.ACTIVE
                                    ? __('Organization is fully operational')
                                    : tenant.status === TenantStatus.SUSPENDED
                                      ? __('Access is restricted')
                                      : __('Tenant is archived')}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">{__('Total Members')}</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{tenant.users_count ?? members.length}</div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                {__('Active users associated with this tenant')}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">{__('Organization Slug')}</CardTitle>
                            <Shield className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-lg font-mono font-medium truncate" title={tenant.slug}>
                                {tenant.slug}
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                {__('Unique platform identifier')}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">{__('Created Date')}</CardTitle>
                            <Calendar className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-lg font-medium">
                                {tenant.created_at ? new Date(tenant.created_at).toLocaleDateString() : '-'}
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                {tenant.created_at ? new Date(tenant.created_at).toLocaleTimeString() : '-'}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Organization Members Card & Table */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>{__('Organization Members')}</CardTitle>
                                <CardDescription>
                                    {__('All users with access to this tenant organization and their assigned roles.')}
                                </CardDescription>
                            </div>
                            <Badge variant="secondary">
                                {members.length} {members.length === 1 ? __('Member') : __('Members')}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {members.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-8 text-center">
                                <Users className="mx-auto h-12 w-12 text-muted-foreground/50" />
                                <h3 className="mt-4 text-lg font-medium">{__('No members found')}</h3>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {__('This tenant organization currently has no associated members.')}
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-hidden rounded-md border">
                                <Table>
                                    <TableHeader className="bg-muted/50">
                                        <TableRow>
                                            <TableHead className="w-[300px]">{__('User')}</TableHead>
                                            <TableHead>{__('Email')}</TableHead>
                                            <TableHead>{__('Role')}</TableHead>
                                            <TableHead>{__('Membership Status')}</TableHead>
                                            <TableHead className="text-right">{__('Joined')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {members.map((member) => (
                                            <TableRow key={member.id} className="hover:bg-muted/25">
                                                <TableCell className="font-medium">
                                                    <div className="flex items-center gap-2">
                                                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary uppercase">
                                                            {member.name.charAt(0)}
                                                        </div>
                                                        <span>{member.name}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    <div className="flex items-center gap-1.5 text-sm">
                                                        <Mail className="h-3.5 w-3.5" />
                                                        <span>{member.email}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>{getRoleBadge(member.role)}</TableCell>
                                                <TableCell>{getMembershipStatusBadge(member.status)}</TableCell>
                                                <TableCell className="text-right text-sm text-muted-foreground">
                                                    {member.joined_at ? new Date(member.joined_at).toLocaleDateString() : '-'}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Edit Sheet */}
            <CreateEditSheet
                open={editSheetOpen}
                onOpenChange={setEditSheetOpen}
                tenant={tenant}
                mode="edit"
            />

            {/* Suspend Sheet */}
            <SuspendTenantSheet
                open={suspendSheetOpen}
                onClose={() => setSuspendSheetOpen(false)}
                tenant={tenant}
            />
        </AdminLayout>
    );
}
