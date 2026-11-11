import { store as usersStore, update as usersUpdate } from '@/actions/App/Http/Controllers/Admin/UserController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { type Role, type User } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Form } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Save, Shield, User as UserIcon } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

interface UserSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user?: User | null;
    roles: Role[];
    mode: 'create' | 'edit';
}

interface UserRole {
    id: number;
    name: string;
}

export default function CreateEditSheet({ open, onOpenChange, user, roles, mode }: UserSheetProps) {
    const { __ } = useLocalizer();
    const isEditing = mode === 'edit' && user;

    // Get initial user roles for editing
    const userRoles = useMemo(
        () => (isEditing && user?.roles && Array.isArray(user.roles) ? (user.roles as UserRole[]) : []),
        [isEditing, user?.roles],
    );

    // Role selection state
    const [selectedRoles, setSelectedRoles] = useState<number[]>(() => userRoles.map((r: UserRole) => r.id));

    const allRoleIds = useMemo(() => roles.map((role) => role.id), [roles]);
    const selectedCount = selectedRoles.length;
    const totalCount = roles.length;
    const allSelected = useMemo(() => allRoleIds.length > 0 && allRoleIds.every((id) => selectedRoles.includes(id)), [allRoleIds, selectedRoles]);

    const handleRoleChange = useCallback((roleId: number, checked: boolean) => {
        setSelectedRoles((prev) => (checked ? [...prev, roleId] : prev.filter((id) => id !== roleId)));
    }, []);

    const handleSelectAllRoles = useCallback(() => {
        setSelectedRoles(allSelected ? [] : allRoleIds);
    }, [allSelected, allRoleIds]);

    // Handle sheet state changes
    useEffect(() => {
        if (open) {
            document.body.classList.add('sheet-overlay-cursor-blocked');
            // Reset role selection when opening
            setSelectedRoles(userRoles.map((r: UserRole) => r.id));
        } else {
            document.body.classList.remove('sheet-overlay-cursor-blocked');
        }

        return () => {
            document.body.classList.remove('sheet-overlay-cursor-blocked');
        };
    }, [open, userRoles]);

    // Prevent outside click closing
    const handleOpenChange = useCallback(
        (open: boolean) => {
            if (!open) return; // Only allow explicit close actions
            onOpenChange(open);
        },
        [onOpenChange],
    );

    // Close sheet handler
    const handleClose = useCallback(() => {
        onOpenChange(false);
    }, [onOpenChange]);

    // Success handler
    const handleSuccess = useCallback(() => {
        onOpenChange(false);
    }, [onOpenChange]);

    return (
        <Sheet open={open} onOpenChange={handleOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-lg [&>button]:hidden">
                <Form
                    action={isEditing ? usersUpdate({ user: user.id }).url : usersStore.url()}
                    method={isEditing ? 'put' : 'post'}
                    transform={(data) => ({ ...data, roles: selectedRoles })}
                    onSuccess={handleSuccess}
                    resetOnSuccess={!isEditing}
                    disableWhileProcessing
                    options={{
                        preserveScroll: true,
                    }}
                >
                    {({ errors, processing, recentlySuccessful }) => (
                        <div className="flex h-full flex-col">
                            <SheetHeader className="border-b">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                            <UserIcon className="h-5 w-5 text-primary" />
                                        </div>
                                        <div>
                                            <SheetTitle>{isEditing ? `${__('Edit User:')} ${user?.name}` : __('Create New User')}</SheetTitle>
                                            <SheetDescription>
                                                {isEditing
                                                    ? __('Update user information and role assignments')
                                                    : __('Add a new user to the system with appropriate roles')}
                                            </SheetDescription>
                                        </div>
                                    </div>
                                </div>
                            </SheetHeader>

                            <div className="flex-1 space-y-6 px-4 py-2">
                                {/* Success Message */}
                                {recentlySuccessful && (
                                    <div className="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950/50">
                                        <div className="flex items-center gap-2">
                                            <CheckCircle2 className="h-4 w-4 text-green-600 dark:text-green-400" />
                                            <p className="text-sm text-green-700 dark:text-green-300">
                                                User {isEditing ? 'updated' : 'created'} successfully!
                                            </p>
                                        </div>
                                    </div>
                                )}

                                {/* User Information */}
                                <div className="space-y-4">
                                    <div className="flex items-center gap-2">
                                        <UserIcon className="h-4 w-4 text-muted-foreground" />
                                        <h3 className="font-medium">{__('User Information')}</h3>
                                    </div>

                                    <div className="space-y-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="name" className="text-sm font-medium">
                                                {__('Full Name')} <span className="text-destructive">*</span>
                                            </Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                type="text"
                                                defaultValue={isEditing ? user?.name : ''}
                                                placeholder={__('Enter full name')}
                                                className={errors.name ? 'border-destructive focus-visible:ring-destructive' : ''}
                                                required
                                            />
                                            {errors.name && (
                                                <div className="flex items-center gap-1 text-sm text-destructive">
                                                    <AlertCircle className="h-3 w-3" />
                                                    {errors.name}
                                                </div>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="email" className="text-sm font-medium">
                                                {__('Email Address')} <span className="text-destructive">*</span>
                                            </Label>
                                            <Input
                                                id="email"
                                                name="email"
                                                type="email"
                                                defaultValue={isEditing ? user?.email : ''}
                                                placeholder={__('user@example.com')}
                                                className={errors.email ? 'border-destructive focus-visible:ring-destructive' : ''}
                                                required
                                            />
                                            {errors.email && (
                                                <div className="flex items-center gap-1 text-sm text-destructive">
                                                    <AlertCircle className="h-3 w-3" />
                                                    {errors.email}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <Separator />

                                {/* Password Settings */}
                                <div className="space-y-4">
                                    <h4 className="flex items-center gap-2 text-sm font-medium">
                                        {__('Password Settings')}
                                        {isEditing && (
                                            <span className="text-xs font-normal text-muted-foreground">({__('leave blank to keep current')})</span>
                                        )}
                                    </h4>

                                    <div className="space-y-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="password" className="text-sm font-medium">
                                                {__('Password')} {!isEditing && <span className="text-destructive">*</span>}
                                            </Label>
                                            <Input
                                                id="password"
                                                name="password"
                                                type="password"
                                                placeholder={__('Enter password')}
                                                className={errors.password ? 'border-destructive focus-visible:ring-destructive' : ''}
                                                required={!isEditing}
                                            />
                                            {errors.password && (
                                                <div className="flex items-center gap-1 text-sm text-destructive">
                                                    <AlertCircle className="h-3 w-3" />
                                                    {errors.password}
                                                </div>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="password_confirmation" className="text-sm font-medium">
                                                {__('Confirm Password')} {!isEditing && <span className="text-destructive">*</span>}
                                            </Label>
                                            <Input
                                                id="password_confirmation"
                                                name="password_confirmation"
                                                type="password"
                                                placeholder={__('Confirm password')}
                                                className={errors.password_confirmation ? 'border-destructive focus-visible:ring-destructive' : ''}
                                                required={!isEditing}
                                            />
                                            {errors.password_confirmation && (
                                                <div className="flex items-center gap-1 text-sm text-destructive">
                                                    <AlertCircle className="h-3 w-3" />
                                                    {errors.password_confirmation}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <Separator />

                                {/* Role Assignment */}
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Shield className="h-4 w-4 text-muted-foreground" />
                                            <h3 className="font-medium">{__('Role Assignment')}</h3>
                                        </div>
                                        {roles.length > 0 && (
                                            <Badge variant="outline" className="text-xs">
                                                {selectedCount} of {totalCount} selected
                                            </Badge>
                                        )}
                                    </div>

                                    {roles.length > 0 && (
                                        <div className="flex justify-end">
                                            <Button type="button" variant="ghost" size="sm" onClick={handleSelectAllRoles} disabled={processing}>
                                                {selectedCount === totalCount ? __('Clear All') : __('Select All')}
                                            </Button>
                                        </div>
                                    )}

                                    {roles.length === 0 ? (
                                        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-6 text-center">
                                            <Shield className="mb-2 h-8 w-8 text-muted-foreground/50" />
                                            <p className="mb-1 text-sm text-muted-foreground">{__('No roles available')}</p>
                                            <p className="text-xs text-muted-foreground">{__('Create roles first to assign them to users')}</p>
                                        </div>
                                    ) : (
                                        <div className="space-y-2">
                                            {roles.map((role) => (
                                                <div
                                                    key={role.id}
                                                    className={`flex items-center space-x-3 rounded-lg border p-3 transition-colors ${
                                                        selectedRoles.includes(role.id)
                                                            ? 'border-primary/20 bg-primary/5'
                                                            : 'border-border hover:border-primary/20 hover:bg-muted/50'
                                                    }`}
                                                >
                                                    <Checkbox
                                                        id={`role-${role.id}`}
                                                        checked={selectedRoles.includes(role.id)}
                                                        onCheckedChange={(checked) => handleRoleChange(role.id, checked as boolean)}
                                                        disabled={processing}
                                                    />
                                                    <Label htmlFor={`role-${role.id}`} className="flex-1 cursor-pointer">
                                                        <div className="flex items-center justify-between">
                                                            <span className="font-medium capitalize">{role.name}</span>
                                                            {selectedRoles.includes(role.id) && <CheckCircle2 className="h-4 w-4 text-primary" />}
                                                        </div>
                                                    </Label>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                    {errors.roles && (
                                        <div className="flex items-center gap-1 text-sm text-destructive">
                                            <AlertCircle className="h-3 w-3" />
                                            {errors.roles}
                                        </div>
                                    )}
                                </div>

                                {/* Error Summary */}
                                {Object.keys(errors).length > 0 && (
                                    <div className="rounded-lg border border-destructive/50 bg-destructive/5 p-4">
                                        <div className="mb-2 flex items-center gap-2">
                                            <AlertCircle className="h-4 w-4 text-destructive" />
                                            <h4 className="font-medium text-destructive">{__('Please fix the following errors:')}</h4>
                                        </div>
                                        <ul className="space-y-1 text-sm">
                                            {Object.entries(errors).map(([key, message]) => (
                                                <li key={key} className="flex items-center gap-2 text-destructive">
                                                    <div className="h-1 w-1 rounded-full bg-current" />
                                                    {message}
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                            </div>

                            <SheetFooter className="border-t">
                                <Button type="button" variant="outline" onClick={handleClose} disabled={processing} className="cursor-pointer">
                                    {__('Cancel')}
                                </Button>
                                <Button type="submit" disabled={processing} className="min-w-[120px] cursor-pointer">
                                    {processing ? (
                                        <div className="flex items-center gap-2">
                                            <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                            {isEditing ? __('Updating...') : __('Creating...')}
                                        </div>
                                    ) : (
                                        <>
                                            <Save className="mr-2 h-4 w-4" />
                                            {isEditing ? __('Update User') : __('Create User')}
                                        </>
                                    )}
                                </Button>
                            </SheetFooter>
                        </div>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}
