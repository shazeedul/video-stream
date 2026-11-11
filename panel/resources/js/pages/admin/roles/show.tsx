import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/admin-layout';
import { dashboard } from '@/routes/admin';
import { edit as rolesEdit, show as roleShow, index as rolesIndex } from '@/routes/admin/roles';
import { type BreadcrumbItem, type Role } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Can } from '@devwizard/laravel-react-permissions';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Calendar, Edit, Shield, Users } from 'lucide-react';

interface Props {
    role: Role;
}

export default function Show({ role }: Props) {
    const { __ } = useLocalizer();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: __('System'), href: dashboard().url },
        { title: __('Roles'), href: rolesIndex().url },
        { title: role.display_name, href: roleShow({ role: role.id }).url },
    ];
    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`${__('Role')} - ${role.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button asChild variant="ghost" size="sm">
                        <Link href={rolesIndex().url}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div className="flex-1">
                        <div className="flex items-center gap-3">
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight">{role.name}</h1>
                                <p className="text-sm text-muted-foreground">{role.display_name}</p>
                            </div>
                            {role.is_default ? <Badge>{__('Default')}</Badge> : <Badge variant="secondary">{__('Custom')}</Badge>}
                        </div>
                        <p className="text-muted-foreground">{__('Role details and permissions')}</p>
                    </div>
                    {!role.is_default && (
                        <>
                            <Can permission="roles.update">
                                <Button asChild>
                                    <Link href={rolesEdit({ role: role.id }).url}>
                                        <Edit className="mr-2 h-4 w-4" />
                                        {__('Edit Role')}
                                    </Link>
                                </Button>
                            </Can>
                        </>
                    )}
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        {/* Permissions */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center justify-between">
                                    <span>{__('Permissions')}</span>
                                    <Badge variant="outline">
                                        {role.permissions?.length || 0} {__('permissions')}
                                    </Badge>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {!role.permissions || role.permissions.length === 0 ? (
                                    <div className="py-8 text-center text-muted-foreground">
                                        <Shield className="mx-auto mb-4 h-8 w-8 opacity-50" />
                                        <p>{__('No permissions assigned to this role')}</p>
                                    </div>
                                ) : (
                                    <div className="space-y-6">
                                        {Object.entries(
                                            role.permissions.reduce(
                                                (groups, permission) => {
                                                    const group = permission.group || 'Other';
                                                    if (!groups[group]) groups[group] = [];
                                                    groups[group].push(permission);
                                                    return groups;
                                                },
                                                {} as Record<string, typeof role.permissions>,
                                            ),
                                        ).map(([group, permissions]) => (
                                            <div key={group} className="space-y-3">
                                                <h4 className="text-sm font-medium text-muted-foreground capitalize">
                                                    {group} ({permissions.length})
                                                </h4>
                                                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                                    {permissions.map((permission) => (
                                                        <div
                                                            key={permission.id}
                                                            className="flex flex-col gap-1 rounded-md border border-muted bg-muted/25 p-2"
                                                        >
                                                            <div className="flex items-center gap-2">
                                                                <Shield className="h-3 w-3 text-muted-foreground" />
                                                                <span className="text-sm font-medium">
                                                                    {permission.display_name || permission.name}
                                                                </span>
                                                            </div>
                                                            {permission.display_name && permission.display_name !== permission.name && (
                                                                <span className="text-xs text-muted-foreground">{permission.name}</span>
                                                            )}
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Role Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Shield className="h-5 w-5" />
                                    {__('Role Information')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label className="text-sm font-medium text-muted-foreground">{__('Display Name')}</Label>
                                        <p className="mt-1 font-medium">{role.display_name}</p>
                                    </div>
                                    <div>
                                        <Label className="text-sm font-medium text-muted-foreground">{__('Role Name')}</Label>
                                        <p className="mt-1 text-sm text-muted-foreground">{role.name}</p>
                                    </div>
                                    <div>
                                        <Label className="text-sm font-medium text-muted-foreground">{__('Guard Name')}</Label>
                                        <p className="mt-1">
                                            <Badge variant="outline">{role.guard_name}</Badge>
                                        </p>
                                    </div>
                                    <div>
                                        <Label className="text-sm font-medium text-muted-foreground">{__('Type')}</Label>
                                        <p className="mt-1">
                                            {role.is_default ? (
                                                <Badge>{__('Default Role')}</Badge>
                                            ) : (
                                                <Badge variant="secondary">{__('Custom Role')}</Badge>
                                            )}
                                        </p>
                                    </div>
                                    <div>
                                        <Label className="text-sm font-medium text-muted-foreground">{__('Permissions Count')}</Label>
                                        <p className="mt-1 font-medium">{role.permissions?.length || 0}</p>
                                    </div>
                                </div>

                                {role.is_default && (
                                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/50">
                                        <p className="text-sm text-amber-700 dark:text-amber-300">
                                            <strong>{__('Default Role:')}</strong>{' '}
                                            {__(
                                                'This role is protected and cannot be deleted. Basic information cannot be modified, but permissions can be adjusted if needed.',
                                            )}
                                        </p>
                                    </div>
                                )}

                                {role.description && (
                                    <div>
                                        <Label className="text-sm font-medium text-muted-foreground">{__('Description')}</Label>
                                        <p className="mt-1 text-sm">{role.description}</p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                        {/* Quick Stats */}
                        <Card>
                            <CardHeader>
                                <CardTitle>{__('Quick Stats')}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center gap-3">
                                    <div className="rounded-full bg-blue-100 p-2 dark:bg-blue-900/20">
                                        <Shield className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">{__('Permissions')}</p>
                                        <p className="font-medium">{role.permissions?.length || 0}</p>
                                    </div>
                                </div>

                                <div className="flex items-center gap-3">
                                    <div className="rounded-full bg-green-100 p-2 dark:bg-green-900/20">
                                        <Users className="h-4 w-4 text-green-600 dark:text-green-400" />
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">{__('Guard')}</p>
                                        <p className="font-medium">{role.guard_name}</p>
                                    </div>
                                </div>

                                <div className="flex items-center gap-3">
                                    <div className="rounded-full bg-purple-100 p-2 dark:bg-purple-900/20">
                                        <Calendar className="h-4 w-4 text-purple-600 dark:text-purple-400" />
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">{__('Created')}</p>
                                        <p className="font-medium">{new Date(role.created_at).toLocaleDateString()}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Actions */}
                        <Card>
                            <CardHeader>
                                <CardTitle>{__('Actions')}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {!role.is_default && (
                                    <>
                                        <Can permission="roles.update">
                                            <Button asChild className="w-full">
                                                <Link href={rolesEdit({ role: role.id }).url}>
                                                    <Edit className="mr-2 h-4 w-4" />
                                                    {__('Edit Role')}
                                                </Link>
                                            </Button>
                                        </Can>
                                    </>
                                )}

                                <Button asChild variant="outline" className="w-full">
                                    <Link href={rolesIndex().url}>
                                        <ArrowLeft className="mr-2 h-4 w-4" />
                                        {__('Back to Roles')}
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}

function Label({ className, children, ...props }: React.ComponentProps<'label'>) {
    return (
        <label className={className} {...props}>
            {children}
        </label>
    );
}
