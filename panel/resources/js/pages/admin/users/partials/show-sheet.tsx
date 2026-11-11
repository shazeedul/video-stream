import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { UserStatus } from '@/enums';
import { User } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { AlertCircle, Calendar, CheckCircle2, Mail, Shield, User as UserIcon, X } from 'lucide-react';
import { useCallback, useEffect } from 'react';

interface Props {
    user:
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
        | null;
    open: boolean;
    onClose: () => void;
}

export default function ShowSheet({ user, open, onClose }: Props) {
    const { __ } = useLocalizer();
    // Handle sheet state changes
    useEffect(() => {
        if (open) {
            document.body.classList.add('sheet-overlay-cursor-blocked');
        } else {
            document.body.classList.remove('sheet-overlay-cursor-blocked');
        }

        return () => {
            document.body.classList.remove('sheet-overlay-cursor-blocked');
        };
    }, [open]);

    // Prevent outside click closing
    const handleOpenChange = useCallback(
        (open: boolean) => {
            if (!open) return; // Only allow explicit close actions
            onClose();
        },
        [onClose],
    );

    // Close sheet handler
    const handleClose = useCallback(() => {
        onClose();
    }, [onClose]);

    if (!user) return null;

    // Get unique permissions from all roles with safety checks
    const uniquePermissions =
        user.roles
            ?.flatMap((role) => role.permissions || [])
            .filter((permission) => permission && permission.id) // Filter out undefined/null permissions
            .filter((permission, index, self) => self.findIndex((p) => p && p.id === permission.id) === index) || [];

    return (
        <Sheet open={open} onOpenChange={handleOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-lg [&>button]:hidden">
                <div className="flex h-full flex-col">
                    <SheetHeader className="border-b">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                    <UserIcon className="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <SheetTitle>
                                        {__('User Details:')} {user.name}
                                    </SheetTitle>
                                    <SheetDescription>{__('View user information and role assignments')}</SheetDescription>
                                </div>
                            </div>
                            <Button variant="ghost" size="sm" onClick={handleClose}>
                                <X className="h-4 w-4" />
                            </Button>
                        </div>
                    </SheetHeader>

                    <div className="flex-1 space-y-6 px-4 py-2">
                        {/* User Information */}
                        <div className="space-y-4">
                            <div className="flex items-center gap-2">
                                <UserIcon className="h-4 w-4 text-muted-foreground" />
                                <h3 className="font-medium">{__('User Information')}</h3>
                            </div>

                            <div className="space-y-4">
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-muted-foreground">{__('Full Name')}</label>
                                    <p className="rounded-lg border bg-muted/50 px-3 py-2 text-sm font-medium">{user.name}</p>
                                </div>

                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-muted-foreground">{__('Email Address')}</label>
                                    <div className="flex items-center gap-2">
                                        <Mail className="h-4 w-4 text-muted-foreground" />
                                        <p className="text-sm">{user.email}</p>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-muted-foreground">{__('Email Verification')}</label>
                                    <div className="flex items-center gap-2">
                                        {user.email_verified_at ? (
                                            <>
                                                <CheckCircle2 className="h-4 w-4 text-green-500" />
                                                <Badge
                                                    variant="default"
                                                    className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200"
                                                >
                                                    {__('Verified')}
                                                </Badge>
                                            </>
                                        ) : (
                                            <>
                                                <X className="h-4 w-4 text-yellow-500" />
                                                <Badge
                                                    variant="secondary"
                                                    className="bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200"
                                                >
                                                    {__('Unverified')}
                                                </Badge>
                                            </>
                                        )}
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-muted-foreground">{__('Account Status')}</label>
                                    <div className="flex items-center gap-2">
                                        {user.status === UserStatus.ACTIVE && (
                                            <>
                                                <CheckCircle2 className="h-4 w-4 text-green-500" />
                                                <Badge
                                                    variant="default"
                                                    className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200"
                                                >
                                                    {__('Active')}
                                                </Badge>
                                            </>
                                        )}
                                        {user.status === UserStatus.SUSPENDED && (
                                            <>
                                                <AlertCircle className="h-4 w-4 text-red-500" />
                                                <Badge variant="destructive" className="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                    {__('Suspended')}
                                                </Badge>
                                            </>
                                        )}
                                        {user.status === UserStatus.INACTIVE && (
                                            <>
                                                <AlertCircle className="h-4 w-4 text-gray-500" />
                                                <Badge variant="secondary" className="bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200">
                                                    {__('Inactive')}
                                                </Badge>
                                            </>
                                        )}
                                    </div>
                                </div>

                                {user.status_reason && (
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium text-muted-foreground">{__('Status Reason')}</label>
                                        <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-950/20">
                                            <p className="text-sm text-amber-900 dark:text-amber-200">{user.status_reason}</p>
                                        </div>
                                    </div>
                                )}

                                {user.status_updated_at && (
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium text-muted-foreground">{__('Status Updated')}</label>
                                        <div className="flex items-center gap-2">
                                            <Calendar className="h-4 w-4 text-muted-foreground" />
                                            <p className="text-sm">
                                                {new Date(user.status_updated_at).toLocaleDateString('en-US', {
                                                    year: 'numeric',
                                                    month: 'long',
                                                    day: 'numeric',
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                })}
                                            </p>
                                        </div>
                                    </div>
                                )}

                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-muted-foreground">{__('Member Since')}</label>
                                    <div className="flex items-center gap-2">
                                        <Calendar className="h-4 w-4 text-muted-foreground" />
                                        <p className="text-sm">
                                            {new Date(user.created_at).toLocaleDateString('en-US', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                            })}
                                        </p>
                                    </div>
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
                                {user.roles.length > 0 && (
                                    <Badge variant="outline" className="text-xs">
                                        {user.roles.length} {user.roles.length === 1 ? __('role') : __('roles')} {__('assigned')}
                                    </Badge>
                                )}
                            </div>

                            {user.roles.length === 0 ? (
                                <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-6 text-center">
                                    <Shield className="mb-2 h-8 w-8 text-muted-foreground/50" />
                                    <p className="mb-1 text-sm text-muted-foreground">{__('No roles assigned')}</p>
                                    <p className="text-xs text-muted-foreground">{__('This user has no role assignments')}</p>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">{__('Assigned Roles')}</label>
                                        <div className="mt-2 space-y-2">
                                            {user.roles.map((role) => (
                                                <div
                                                    key={role.id}
                                                    className="flex items-center space-x-3 rounded-lg border border-primary/20 bg-primary/5 p-3"
                                                >
                                                    <CheckCircle2 className="h-4 w-4 text-primary" />
                                                    <div className="flex-1">
                                                        <span className="font-medium capitalize">{role.name}</span>
                                                        {role.permissions.length > 0 && (
                                                            <p className="text-xs text-muted-foreground">
                                                                {role.permissions.length}{' '}
                                                                {role.permissions.length === 1 ? __('permission') : __('permissions')}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    {uniquePermissions.length > 0 && (
                                        <div>
                                            <label className="text-sm font-medium text-muted-foreground">
                                                {__('All Permissions')} ({uniquePermissions.length})
                                            </label>
                                            <div className="mt-2 flex flex-wrap gap-1">
                                                {uniquePermissions.map((permission) => (
                                                    <Badge key={permission.id} variant="secondary" className="text-xs">
                                                        {permission.name}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>

                    <SheetFooter className="border-t">
                        <Button variant="outline" onClick={handleClose} className="w-full">
                            {__('Close')}
                        </Button>
                    </SheetFooter>
                </div>
            </SheetContent>
        </Sheet>
    );
}
