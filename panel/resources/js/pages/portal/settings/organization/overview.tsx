import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SettingsLayout from '@/pages/portal/settings/layout';
import { overview, update } from '@/routes/portal/settings/organization';
import { type BreadcrumbItem } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Head, useForm } from '@inertiajs/react';
import { Building2, Calendar, ShieldCheck } from 'lucide-react';
import { type FormEventHandler } from 'react';

interface Organization {
    id: string;
    name: string;
    slug: string;
    status: string;
    settings?: Record<string, unknown>;
    created_at?: string;
}

interface Props {
    readonly organization: Organization;
    readonly userRole?: string;
}

export default function OrganizationOverview({ organization, userRole }: Props) {
    const { __ } = useLocalizer();
    const isOwnerOrAdmin = userRole === 'owner' || userRole === 'admin';

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('Organization Settings'),
            href: overview().url,
        },
    ];

    const { data, setData, put, processing, errors } = useForm({
        name: organization.name,
        slug: organization.slug,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(update().url);
    };

    const formattedDate = organization.created_at
        ? new Date(organization.created_at).toLocaleDateString(undefined, {
              year: 'numeric',
              month: 'long',
              day: 'numeric',
          })
        : null;

    return (
        <SettingsLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Organization Settings')} />

            <div className="space-y-6">
                <div>
                    <h2 className="text-2xl font-bold tracking-tight">{__('Organization Profile')}</h2>
                    <p className="text-muted-foreground">
                        {__("Manage your organization's general information and settings.")}
                    </p>
                </div>

                <div className="grid gap-6">
                    <Card>
                        <CardHeader>
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <Building2 className="h-5 w-5 text-muted-foreground" />
                                        <CardTitle className="text-xl">{organization.name}</CardTitle>
                                    </div>
                                    <CardDescription>
                                        {__('Slug: :slug', { slug: organization.slug })}
                                    </CardDescription>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant={organization.status === 'active' ? 'default' : 'destructive'}>
                                        {organization.status}
                                    </Badge>
                                    {userRole && (
                                        <Badge variant="secondary" className="gap-1 capitalize">
                                            <ShieldCheck className="h-3.5 w-3.5" />
                                            {userRole}
                                        </Badge>
                                    )}
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="name">{__('Organization Name')}</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder={__('Organization Name')}
                                        disabled={!isOwnerOrAdmin || processing}
                                        required
                                    />
                                    {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="slug">{__('Organization Slug')}</Label>
                                    <Input
                                        id="slug"
                                        value={data.slug}
                                        onChange={(e) => setData('slug', e.target.value)}
                                        placeholder={__('Organization Slug')}
                                        disabled={!isOwnerOrAdmin || processing}
                                        required
                                    />
                                    {errors.slug && <p className="text-sm text-destructive">{errors.slug}</p>}
                                </div>

                                {formattedDate && (
                                    <div className="flex items-center gap-2 pt-1 text-sm text-muted-foreground">
                                        <Calendar className="h-4 w-4" />
                                        <span>{__('Created on :date', { date: formattedDate })}</span>
                                    </div>
                                )}

                                {isOwnerOrAdmin ? (
                                    <Button type="submit" disabled={processing}>
                                        {processing ? __('Saving...') : __('Save Changes')}
                                    </Button>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        {__('You have view-only access to this organization profile.')}
                                    </p>
                                )}
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </SettingsLayout>
    );
}
