import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { TenantStatus } from '@/enums';
import { status as tenantsStatus } from '@/routes/admin/tenants';
import { type Tenant } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { useForm } from '@inertiajs/react';
import { AlertTriangle, Building2, ShieldAlert } from 'lucide-react';
import React from 'react';

interface Props {
    tenant: Tenant | null;
    open: boolean;
    onClose: () => void;
}

export default function SuspendTenantSheet({ tenant, open, onClose }: Props) {
    const { __ } = useLocalizer();
    const { patch, processing } = useForm({
        status: TenantStatus.SUSPENDED,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!tenant) return;

        patch(tenantsStatus({ tenant: tenant.id }).url, {
            preserveScroll: true,
            onSuccess: () => {
                onClose();
            },
        });
    };

    return (
        <Sheet open={open} onOpenChange={onClose}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                <SheetHeader className="border-b pb-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-destructive/10">
                            <ShieldAlert className="h-5 w-5 text-destructive" />
                        </div>
                        <div>
                            <SheetTitle>{__('Suspend Organization')}</SheetTitle>
                            <SheetDescription>
                                {__('Temporarily suspend access for this tenant')}
                            </SheetDescription>
                        </div>
                    </div>
                </SheetHeader>

                <form onSubmit={handleSubmit} className="flex-1 space-y-6 px-4 py-4">
                    {/* Warning Callout */}
                    <div className="rounded-lg border border-destructive/20 bg-destructive/10 p-4 text-destructive">
                        <div className="flex gap-3">
                            <AlertTriangle className="h-5 w-5 shrink-0" />
                            <div className="space-y-1">
                                <h4 className="font-semibold text-sm">{__('Implications of Suspension')}</h4>
                                <ul className="list-disc pl-4 text-xs space-y-1 text-destructive/90">
                                    <li>{__('All organization members will immediately lose access to this tenant workspace.')}</li>
                                    <li>{__('Any running video processing or queue jobs tied to this tenant may be halted.')}</li>
                                    <li>{__('API tokens and webhooks associated with this tenant will be rejected.')}</li>
                                    <li>{__('Organization data will remain intact and can be reactivated at any time.')}</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    {/* Tenant Information Summary */}
                    {tenant && (
                        <div className="rounded-lg border bg-muted/40 p-4 space-y-3">
                            <div className="flex items-center gap-2">
                                <Building2 className="h-4 w-4 text-muted-foreground" />
                                <span className="font-medium text-sm">{__('Organization Details')}</span>
                            </div>
                            <div className="grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span className="text-muted-foreground">{__('Name')}:</span>
                                    <p className="font-medium text-foreground">{tenant.name}</p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">{__('Slug')}:</span>
                                    <p className="font-mono text-foreground">{tenant.slug}</p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">{__('Total Members')}:</span>
                                    <p className="font-medium text-foreground">{tenant.users_count ?? 0}</p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">{__('Current Status')}:</span>
                                    <p className="font-medium capitalize text-foreground">{tenant.status}</p>
                                </div>
                            </div>
                        </div>
                    )}

                    <SheetFooter className="gap-2 pt-4 border-t">
                        <Button type="button" variant="outline" onClick={onClose} disabled={processing}>
                            {__('Cancel')}
                        </Button>
                        <Button type="submit" variant="destructive" disabled={processing || !tenant}>
                            <ShieldAlert className="mr-2 h-4 w-4" />
                            {processing ? __('Suspending...') : __('Suspend Tenant')}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
