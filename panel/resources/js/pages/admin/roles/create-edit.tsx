import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { dashboard } from '@/routes/admin';
import { create as rolesCreate, index as rolesIndex, store as rolesStore, update as rolesUpdate } from '@/routes/admin/roles';
import { type BreadcrumbItem, type Permission, type Role } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Form, Head, Link } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, CheckCircle2, Plus, Save } from 'lucide-react';
import { useCallback, useState } from 'react';

interface Props {
    role?: Role;
    permissions: Record<string, Permission[]>;
    mode: 'create' | 'edit';
}

export default function CreateEdit({ role, permissions, mode }: Props) {
    const { __ } = useLocalizer();
    const isEditing = mode === 'edit' && role;
    const [selectedPermissions, setSelectedPermissions] = useState<number[]>(isEditing ? role.permissions?.map((p) => p.id) || [] : []);
    const [roleName, setRoleName] = useState(isEditing ? role.name : '');
    const [displayName, setDisplayName] = useState(isEditing ? role.display_name : '');
    const [displayNameModified, setDisplayNameModified] = useState<boolean>(isEditing ? true : false);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('System'),
            href: dashboard().url,
        },
        {
            title: __('Roles'),
            href: rolesIndex().url,
        },
        {
            title: isEditing ? __('Edit') : __('Create'),
            href: isEditing ? '#' : rolesCreate().url,
        },
    ];

    const handlePermissionChange = useCallback(
        (permissionId: number, checked: boolean) => {
            const newPermissions = checked ? [...selectedPermissions, permissionId] : selectedPermissions.filter((id) => id !== permissionId);
            setSelectedPermissions(newPermissions);
        },
        [selectedPermissions],
    );

    const handleGroupToggle = useCallback(
        (groupPermissions: Permission[], checked: boolean) => {
            const groupIds = groupPermissions.map((p) => p.id);
            let newPermissions: number[];

            if (checked) {
                newPermissions = [...new Set([...selectedPermissions, ...groupIds])];
            } else {
                newPermissions = selectedPermissions.filter((id) => !groupIds.includes(id));
            }

            setSelectedPermissions(newPermissions);
        },
        [selectedPermissions],
    );

    const getGroupCheckedState = useCallback(
        (groupPermissions: Permission[]) => {
            const groupIds = groupPermissions.map((p) => p.id);
            const checkedCount = groupIds.filter((id) => selectedPermissions.includes(id)).length;

            if (checkedCount === 0) return false;
            if (checkedCount === groupIds.length) return true;
            return 'indeterminate';
        },
        [selectedPermissions],
    );

    const handleSelectAll = useCallback(() => {
        const allPermissionIds = Object.values(permissions)
            .flat()
            .map((p) => p.id);
        setSelectedPermissions(allPermissionIds);
    }, [permissions]);

    const handleDeselectAll = useCallback(() => {
        setSelectedPermissions([]);
    }, []);

    // Format role name: lowercase, replace spaces with underscores
    const formatRoleName = useCallback((name: string) => {
        return name
            .toLowerCase()
            .replace(/\s+/g, '_')
            .replace(/[^a-z0-9_.]/g, '');
    }, []);

    // Generate display name from role name
    const generateDisplayName = useCallback((name: string) => {
        return name
            .split(/[_.]/) // Split by underscore or dot
            .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()) // Capitalize each word
            .join(' ');
    }, []);

    // Handle role name change with formatting and auto-suggest
    const handleRoleNameChange = useCallback(
        (value: string) => {
            const formattedName = formatRoleName(value);
            setRoleName(formattedName);

            // Auto-suggest display name only if user hasn't manually modified it
            if (!displayNameModified && formattedName) {
                setDisplayName(generateDisplayName(formattedName));
            }
        },
        [displayNameModified, formatRoleName, generateDisplayName],
    );

    // Handle display name change
    const handleDisplayNameChange = useCallback((value: string) => {
        setDisplayName(value);
        setDisplayNameModified(true);
    }, []);

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={isEditing ? `${__('Edit Role')} - ${role.name}` : __('Create Role')} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button asChild variant="ghost" size="sm">
                            <Link href={rolesIndex().url}>
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">{isEditing ? __('Edit Role') : __('Create Role')}</h1>
                            <p className="text-muted-foreground">
                                {isEditing
                                    ? __('Modify role ":name" and its permissions', { name: role.name })
                                    : __('Create a new role with custom permissions')}
                            </p>
                        </div>
                    </div>
                    {isEditing && role.is_default && (
                        <Badge variant="outline" className="border-amber-200 text-amber-600">
                            {__('Default Role')}
                        </Badge>
                    )}
                </div>

                {isEditing && role.is_default && (
                    <Card className="border-amber-200 bg-amber-50/50 dark:border-amber-800 dark:bg-amber-950/50">
                        <CardContent className="pt-6">
                            <p className="text-sm text-amber-700 dark:text-amber-300">
                                <strong>{__('Note:')}</strong>{' '}
                                {__(
                                    'This is a default role. You cannot modify its basic information, but you can still adjust its permissions if needed.',
                                )}
                            </p>
                        </CardContent>
                    </Card>
                )}

                <Form
                    action={isEditing ? rolesUpdate({ role: role.id }).url : rolesStore().url}
                    method={isEditing ? 'patch' : 'post'}
                    transform={(data) => ({
                        ...data,
                        name: roleName,
                        display_name: displayName,
                        permissions: selectedPermissions,
                    })}
                    resetOnSuccess={!isEditing}
                    disableWhileProcessing
                    options={{
                        preserveScroll: true,
                    }}
                >
                    {({ errors, processing, recentlySuccessful }) => (
                        <div className="grid gap-6 lg:grid-cols-3">
                            {/* Success Message */}
                            {recentlySuccessful && (
                                <div className="lg:col-span-3">
                                    <div className="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950/50">
                                        <div className="flex items-center gap-2">
                                            <CheckCircle2 className="h-4 w-4 text-green-600 dark:text-green-400" />
                                            <p className="text-sm text-green-700 dark:text-green-300">
                                                {__('Role :action successfully!', { action: isEditing ? __('updated') : __('created') })}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            <div className="lg:col-span-2">
                                {/* Permissions */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center justify-between">
                                            <span>{__('Permissions')}</span>
                                            <div className="flex items-center gap-2">
                                                <Badge variant="outline">
                                                    {selectedPermissions.length} {__('selected')}
                                                </Badge>
                                                <div className="flex gap-1">
                                                    <Button type="button" variant="outline" size="sm" onClick={handleSelectAll} disabled={processing}>
                                                        {__('Select All')}
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={handleDeselectAll}
                                                        disabled={processing}
                                                    >
                                                        {__('Clear')}
                                                    </Button>
                                                </div>
                                            </div>
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        {Object.keys(permissions).length === 0 ? (
                                            <div className="py-8 text-center text-muted-foreground">{__('No permissions available')}</div>
                                        ) : (
                                            <div className="space-y-6">
                                                {Object.entries(permissions).map(([group, groupPermissions]) => {
                                                    const checkedState = getGroupCheckedState(groupPermissions);

                                                    return (
                                                        <div key={group} className="space-y-3">
                                                            <div className="flex items-center space-x-2">
                                                                <Checkbox
                                                                    id={`group-${group}`}
                                                                    checked={checkedState === true}
                                                                    ref={(ref) => {
                                                                        if (ref) {
                                                                            const input = ref.querySelector(
                                                                                'input[type="checkbox"]',
                                                                            ) as HTMLInputElement;
                                                                            if (input) {
                                                                                input.indeterminate = checkedState === 'indeterminate';
                                                                            }
                                                                        }
                                                                    }}
                                                                    onCheckedChange={(checked) =>
                                                                        handleGroupToggle(groupPermissions, checked as boolean)
                                                                    }
                                                                    disabled={processing}
                                                                />
                                                                <Label htmlFor={`group-${group}`} className="text-sm font-medium capitalize">
                                                                    {group} ({groupPermissions.length})
                                                                </Label>
                                                            </div>

                                                            <div className="ml-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                                                {groupPermissions.map((permission) => (
                                                                    <div key={permission.id} className="flex items-center space-x-2">
                                                                        <Checkbox
                                                                            id={`permission-${permission.id}`}
                                                                            checked={selectedPermissions.includes(permission.id)}
                                                                            onCheckedChange={(checked) =>
                                                                                handlePermissionChange(permission.id, checked as boolean)
                                                                            }
                                                                            disabled={processing}
                                                                        />
                                                                        <Label
                                                                            htmlFor={`permission-${permission.id}`}
                                                                            className="flex flex-col text-sm"
                                                                        >
                                                                            <span className="font-medium">{permission.display_name}</span>

                                                                            <span className="text-xs text-muted-foreground">{permission.name}</span>
                                                                        </Label>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        )}

                                        {errors.permissions && (
                                            <div className="mt-4 flex items-center gap-1 text-sm text-destructive">
                                                <AlertCircle className="h-3 w-3" />
                                                {errors.permissions}
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            </div>

                            {/* Sidebar */}
                            <div className="space-y-6">
                                {/* Basic Information */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle>{__('Basic Information')}</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="name">
                                                {__('Role Name')} <span className="text-destructive">*</span>
                                            </Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                value={roleName}
                                                onChange={(e) => handleRoleNameChange(e.target.value)}
                                                placeholder={__('Enter role name (e.g., admin, user)')}
                                                disabled={(isEditing && role.is_default) || processing}
                                                className={errors.name ? 'border-destructive' : ''}
                                                required
                                            />
                                            {errors.name && (
                                                <div className="flex items-center gap-1 text-sm text-destructive">
                                                    <AlertCircle className="h-3 w-3" />
                                                    {errors.name}
                                                </div>
                                            )}
                                            <p className="text-sm text-muted-foreground">
                                                {__(
                                                    'Only lowercase letters, numbers, dots (.), and underscores (_) allowed. Spaces will be replaced with underscores.',
                                                )}
                                            </p>
                                            {isEditing && role.is_default && (
                                                <p className="text-sm text-muted-foreground">{__('Cannot modify name of default role')}</p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="display_name">
                                                {__('Display Name')} <span className="text-destructive">*</span>
                                            </Label>
                                            <Input
                                                id="display_name"
                                                name="display_name"
                                                value={displayName}
                                                onChange={(e) => handleDisplayNameChange(e.target.value)}
                                                placeholder={__('Enter display name (e.g., Administrator, Regular User)')}
                                                disabled={(isEditing && role.is_default) || processing}
                                                className={errors.display_name ? 'border-destructive' : ''}
                                                required
                                            />
                                            {errors.display_name && (
                                                <div className="flex items-center gap-1 text-sm text-destructive">
                                                    <AlertCircle className="h-3 w-3" />
                                                    {errors.display_name}
                                                </div>
                                            )}
                                            <p className="text-sm text-muted-foreground">
                                                {__('Human-readable name for the role (auto-suggested from role name)')}
                                            </p>
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="guard_name">{__('Guard Name')}</Label>
                                            <Input
                                                id="guard_name"
                                                name="guard_name"
                                                defaultValue={isEditing ? role.guard_name : 'web'}
                                                placeholder={__('web')}
                                                disabled={(isEditing && role.is_default) || processing}
                                                className={errors.guard_name ? 'border-destructive' : ''}
                                                required
                                            />
                                            {errors.guard_name && (
                                                <div className="flex items-center gap-1 text-sm text-destructive">
                                                    <AlertCircle className="h-3 w-3" />
                                                    {errors.guard_name}
                                                </div>
                                            )}
                                            <p className="text-sm text-muted-foreground">
                                                {__("The guard that this role applies to (usually 'web')")}
                                            </p>
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="description">{__('Description')}</Label>
                                            <Textarea
                                                id="description"
                                                name="description"
                                                defaultValue={isEditing ? role.description || '' : ''}
                                                placeholder={__('Enter role description (optional)')}
                                                disabled={(isEditing && role.is_default) || processing}
                                                className={errors.description ? 'border-destructive' : ''}
                                                rows={3}
                                            />
                                            {errors.description && (
                                                <div className="flex items-center gap-1 text-sm text-destructive">
                                                    <AlertCircle className="h-3 w-3" />
                                                    {errors.description}
                                                </div>
                                            )}
                                            <p className="text-sm text-muted-foreground">
                                                {__("Optional description to explain the role's purpose")}
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Actions */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle>{__('Actions')}</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <Button type="submit" className="w-full" disabled={processing}>
                                            {processing ? (
                                                <div className="flex items-center gap-2">
                                                    <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                    {isEditing ? __('Updating...') : __('Creating...')}
                                                </div>
                                            ) : (
                                                <>
                                                    {isEditing ? <Save className="mr-2 h-4 w-4" /> : <Plus className="mr-2 h-4 w-4" />}
                                                    {isEditing ? __('Update Role') : __('Create Role')}
                                                </>
                                            )}
                                        </Button>

                                        <Button asChild variant="outline" className="w-full" disabled={processing}>
                                            <Link href={rolesIndex().url}>{__('Cancel')}</Link>
                                        </Button>
                                    </CardContent>
                                </Card>

                                {/* Summary */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle>{isEditing ? __('Role Information') : __('Summary')}</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-2 text-sm">
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">{__('Role Name:')}</span>
                                            <span>{isEditing ? role.name : __('Not set')}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">{__('Guard:')}</span>
                                            <span>{isEditing ? role.guard_name : 'web'}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">{__('Permissions:')}</span>
                                            <span>{selectedPermissions.length}</span>
                                        </div>
                                        {isEditing && (
                                            <>
                                                <div className="flex justify-between">
                                                    <span className="text-muted-foreground">{__('Created:')}</span>
                                                    <span>{new Date(role.created_at).toLocaleDateString()}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-muted-foreground">{__('Type:')}</span>
                                                    <span>{role.is_default ? __('Default') : __('Custom')}</span>
                                                </div>
                                            </>
                                        )}
                                    </CardContent>
                                </Card>

                                {/* Error Summary */}
                                {Object.keys(errors).length > 0 && (
                                    <Card className="border-destructive/50">
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2 text-destructive">
                                                <AlertCircle className="h-4 w-4" />
                                                {__('Please fix the following errors:')}
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <ul className="space-y-1 text-sm">
                                                {Object.entries(errors).map(([key, message]) => (
                                                    <li key={key} className="flex items-center gap-2 text-destructive">
                                                        <div className="h-1 w-1 rounded-full bg-current" />
                                                        {message}
                                                    </li>
                                                ))}
                                            </ul>
                                        </CardContent>
                                    </Card>
                                )}
                            </div>
                        </div>
                    )}
                </Form>
            </div>
        </AdminLayout>
    );
}
