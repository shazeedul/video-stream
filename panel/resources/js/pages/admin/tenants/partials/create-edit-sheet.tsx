import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { TenantStatus } from '@/enums';
import { store as tenantsStore, update as tenantsUpdate } from '@/routes/admin/tenants';
import { type Tenant } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { useForm } from '@inertiajs/react';
import { AlertCircle, Building2, Save } from 'lucide-react';
import React, { useEffect, useState } from 'react';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    tenant?: Tenant | null;
    mode: 'create' | 'edit';
}

export default function CreateEditSheet({ open, onOpenChange, tenant, mode }: Props) {
    const { __ } = useLocalizer();
    const isEditing = mode === 'edit' && !!tenant;
    const [slugManuallyEdited, setSlugManuallyEdited] = useState(false);

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        name: '',
        slug: '',
        owner_email: '',
        status: TenantStatus.ACTIVE as string,
    });

    useEffect(() => {
        if (open) {
            if (isEditing && tenant) {
                setData({
                    name: tenant.name,
                    slug: tenant.slug,
                    owner_email: '',
                    status: tenant.status,
                });
                setSlugManuallyEdited(true);
            } else {
                setData({
                    name: '',
                    slug: '',
                    owner_email: '',
                    status: TenantStatus.ACTIVE,
                });
                setSlugManuallyEdited(false);
            }
            clearErrors();
        }
    }, [open, isEditing, tenant, clearErrors, setData]);

    const slugify = (text: string) => {
        return text
            .toString()
            .toLowerCase()
            .trim()
            .replace(/\s+/g, '-')
            .replace(/[^\w-]+/g, '')
            .replace(/--+/g, '-');
    };

    const handleNameChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const newName = e.target.value;
        if (!isEditing && !slugManuallyEdited) {
            setData((prev) => ({
                ...prev,
                name: newName,
                slug: slugify(newName),
            }));
        } else {
            setData('name', newName);
        }
    };

    const handleSlugChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setSlugManuallyEdited(true);
        setData('slug', slugify(e.target.value));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isEditing && tenant) {
            put(tenantsUpdate({ tenant: tenant.id }).url, {
                preserveScroll: true,
                onSuccess: () => {
                    reset();
                    onOpenChange(false);
                },
            });
        } else {
            post(tenantsStore().url, {
                preserveScroll: true,
                onSuccess: () => {
                    reset();
                    onOpenChange(false);
                },
            });
        }
    };

    const handleClose = () => {
        reset();
        clearErrors();
        onOpenChange(false);
    };

    return (
        <Sheet open={open} onOpenChange={handleClose}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                <SheetHeader className="border-b pb-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                            <Building2 className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <SheetTitle>{isEditing ? `${__('Edit Tenant:')} ${tenant?.name}` : __('Create New Tenant')}</SheetTitle>
                            <SheetDescription>
                                {isEditing
                                    ? __('Update tenant information and organization status')
                                    : __('Create a new organization and assign an initial owner')}
                            </SheetDescription>
                        </div>
                    </div>
                </SheetHeader>

                <form onSubmit={handleSubmit} className="flex-1 space-y-6 px-4 py-4">
                    {/* General Errors */}
                    {Object.keys(errors).length > 0 && (
                        <div className="rounded-lg border border-destructive/20 bg-destructive/10 p-3 text-sm text-destructive">
                            <div className="flex items-center gap-2">
                                <AlertCircle className="h-4 w-4 shrink-0" />
                                <span>{__('Please check the form for errors and try again.')}</span>
                            </div>
                        </div>
                    )}

                    {/* Tenant Name */}
                    <div className="space-y-2">
                        <Label htmlFor="tenant-name" className="text-sm font-medium">
                            {__('Tenant Name')} <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="tenant-name"
                            value={data.name}
                            onChange={handleNameChange}
                            placeholder={__('e.g. Acme Corporation')}
                            className={errors.name ? 'border-destructive' : ''}
                            required
                        />
                        {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                    </div>

                    {/* Tenant Slug */}
                    <div className="space-y-2">
                        <Label htmlFor="tenant-slug" className="text-sm font-medium">
                            {__('Tenant Slug')} <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="tenant-slug"
                            value={data.slug}
                            onChange={handleSlugChange}
                            placeholder={__('e.g. acme-corporation')}
                            className={errors.slug ? 'border-destructive' : ''}
                            required
                        />
                        <p className="text-xs text-muted-foreground">
                            {__('Unique identifier used in URLs and tenant isolation.')}
                        </p>
                        {errors.slug && <p className="text-sm text-destructive">{errors.slug}</p>}
                    </div>

                    {/* Owner Email - Create Only */}
                    {!isEditing && (
                        <div className="space-y-2">
                            <Label htmlFor="tenant-owner-email" className="text-sm font-medium">
                                {__('Owner Email')} <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="tenant-owner-email"
                                type="email"
                                value={data.owner_email}
                                onChange={(e) => setData('owner_email', e.target.value)}
                                placeholder={__('owner@example.com')}
                                className={errors.owner_email ? 'border-destructive' : ''}
                                required
                            />
                            <p className="text-xs text-muted-foreground">
                                {__('Email of an existing user who will become the tenant owner.')}
                            </p>
                            {errors.owner_email && <p className="text-sm text-destructive">{errors.owner_email}</p>}
                        </div>
                    )}

                    {/* Status */}
                    <div className="space-y-2">
                        <Label htmlFor="tenant-status" className="text-sm font-medium">
                            {__('Status')} <span className="text-destructive">*</span>
                        </Label>
                        <Select
                            value={data.status}
                            onValueChange={(val) => setData('status', val)}
                        >
                            <SelectTrigger id="tenant-status" className={errors.status ? 'border-destructive' : ''}>
                                <SelectValue placeholder={__('Select status')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={TenantStatus.ACTIVE}>{__('Active')}</SelectItem>
                                <SelectItem value={TenantStatus.SUSPENDED}>{__('Suspended')}</SelectItem>
                                <SelectItem value={TenantStatus.ARCHIVED}>{__('Archived')}</SelectItem>
                            </SelectContent>
                        </Select>
                        {errors.status && <p className="text-sm text-destructive">{errors.status}</p>}
                    </div>

                    <SheetFooter className="gap-2 pt-4 border-t">
                        <Button type="button" variant="outline" onClick={handleClose} disabled={processing}>
                            {__('Cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            <Save className="mr-2 h-4 w-4" />
                            {processing ? (isEditing ? __('Updating...') : __('Creating...')) : isEditing ? __('Update Tenant') : __('Create Tenant')}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
