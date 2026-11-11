import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import PortalLayout from '@/layouts/portal-layout';
import { Head, useForm } from '@inertiajs/react';
import React, { FormEventHandler } from 'react';

interface Organization {
    id: string;
    name: string;
    slug: string;
    status: string;
    settings?: Record<string, unknown>;
    created_at?: string;
}

interface Props {
    organization: Organization;
    userRole?: string;
}

export default function OrganizationOverview({ organization, userRole }: Props) {
    const isOwnerOrAdmin = userRole === 'owner' || userRole === 'admin';

    const { data, setData, put, processing, errors } = useForm({
        name: organization.name,
        slug: organization.slug,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put('/panel/settings/organization');
    };

    return (
        <PortalLayout>
            <Head title="Organization Settings" />

            <div className="space-y-6">
                <div>
                    <h2 className="text-2xl font-bold tracking-tight">Organization Profile</h2>
                    <p className="text-muted-foreground">
                        Manage your organization's general information and settings.
                    </p>
                </div>

                <div className="grid gap-6">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>{organization.name}</CardTitle>
                                    <CardDescription>Slug: {organization.slug}</CardDescription>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge variant="outline">{organization.status}</Badge>
                                    {userRole && <Badge>{userRole}</Badge>}
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {isOwnerOrAdmin ? (
                                <form onSubmit={submit} className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="name">Organization Name</Label>
                                        <Input
                                            id="name"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            placeholder="Organization Name"
                                            required
                                        />
                                        {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="slug">Organization Slug</Label>
                                        <Input
                                            id="slug"
                                            value={data.slug}
                                            onChange={(e) => setData('slug', e.target.value)}
                                            placeholder="organization-slug"
                                            required
                                        />
                                        {errors.slug && <p className="text-sm text-destructive">{errors.slug}</p>}
                                    </div>

                                    <Button type="submit" disabled={processing}>
                                        Save Changes
                                    </Button>
                                </form>
                            ) : (
                                <div className="space-y-2 text-sm text-muted-foreground">
                                    <p>You have view-only access to this organization profile.</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </PortalLayout>
    );
}
