import ConfirmationDialog from '@/components/confirmation-dialog';
import { UserSettingsHeader } from '@/components/shared/settings-header';
import TabNavigation from '@/components/tab-navigation';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAdminSettingsNavigation } from '@/config/useAdminNavigation';
import AdminLayout from '@/layouts/admin-layout';
import { deleteMethod } from '@/routes/admin/settings/profile';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Form, Head, router, usePage } from '@inertiajs/react';
import { AlertCircle, CalendarClock, CheckCircle2, XCircle } from 'lucide-react';
import { useState } from 'react';

interface User {
    id: number;
    name: string;
    email: string;
    scheduled_deletion_at?: string | null;
    days_until_deletion?: number | null;
}

interface DeleteAccountProps {
    user: User;
}

export default function DeleteAccount({ user }: Readonly<DeleteAccountProps>) {
    const { auth } = usePage<SharedData>().props;
    const { __ } = useLocalizer();
    const settingsNavigation = useAdminSettingsNavigation();
    const [showCancelDialog, setShowCancelDialog] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('Delete Account'),
            href: '#',
        },
    ];

    const handleCancelDeletion = () => {
        setShowCancelDialog(false);
        router.post(deleteMethod().url + '/cancel', {}, { preserveScroll: true });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Delete Account')} />

            <TabNavigation header={<UserSettingsHeader user={auth.user} />} navigation={settingsNavigation}>
                {/* Warning Card */}
                <Card className="my-3 border-destructive bg-destructive/5">
                    <CardHeader>
                        <div className="flex items-start gap-3">
                            <AlertCircle className="mt-0.5 h-5 w-5 text-destructive" />
                            <div className="flex-1">
                                <CardTitle className="text-destructive">{__('Danger Zone')}</CardTitle>
                                <CardDescription className="mt-1.5 text-destructive/80">
                                    {__(
                                        'Deleting your account is a permanent action. All your data, including profile information, settings, and preferences will be lost. This action cannot be undone.',
                                    )}
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                {user.scheduled_deletion_at ? (
                    <>
                        {/* Scheduled Deletion Info */}
                        <Card className="border-warning bg-warning/5 my-3">
                            <CardHeader>
                                <div className="flex items-start gap-3">
                                    <CalendarClock className="text-warning mt-0.5 h-5 w-5" />
                                    <div className="flex-1">
                                        <CardTitle className="text-warning">{__('Account Scheduled for Deletion')}</CardTitle>
                                        <CardDescription className="text-warning/80 mt-1.5">
                                            {__(
                                                'Your account will be permanently deleted in :days days. You can cancel this at any time before the deadline.',
                                                { days: user.days_until_deletion || 0 },
                                            )}
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <div className="border-warning/20 bg-warning/5 rounded-lg border p-4">
                                        <div className="flex items-start gap-3">
                                            <CheckCircle2 className="text-warning mt-0.5 h-5 w-5" />
                                            <div className="flex-1">
                                                <p className="text-warning font-medium">{__('Grace Period Active')}</p>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {__(
                                                        'During this period, you can cancel the deletion and restore your account. After the grace period expires, your account will be permanently deleted.',
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex justify-center">
                                        <Button type="button" variant="outline" onClick={() => setShowCancelDialog(true)}>
                                            <XCircle className="mr-2 h-4 w-4" />
                                            {__('Cancel Deletion')}
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle>{__('Schedule Account Deletion')}</CardTitle>
                            <CardDescription>
                                {__(
                                    'Once you schedule your account for deletion, you will have 30 days to cancel. After that, your account and all associated data will be permanently deleted.',
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action={deleteMethod().url + '/schedule'}
                                method="post"
                                options={{
                                    preserveScroll: true,
                                }}
                            >
                                {({ errors, processing }) => (
                                    <div className="space-y-6">
                                        <div>
                                            <h4 className="mb-3 text-sm font-medium">{__('What happens when you delete your account:')}</h4>
                                            <ul className="space-y-2 text-sm text-muted-foreground">
                                                <li className="flex items-start gap-2">
                                                    <XCircle className="mt-0.5 h-4 w-4 flex-shrink-0 text-destructive" />
                                                    <span>{__('Your profile and all personal information will be permanently deleted')}</span>
                                                </li>
                                                <li className="flex items-start gap-2">
                                                    <XCircle className="mt-0.5 h-4 w-4 flex-shrink-0 text-destructive" />
                                                    <span>{__('All your settings and preferences will be erased')}</span>
                                                </li>
                                                <li className="flex items-start gap-2">
                                                    <CheckCircle2 className="text-success mt-0.5 h-4 w-4 flex-shrink-0" />
                                                    <span>{__('You have 30 days to cancel the deletion')}</span>
                                                </li>
                                            </ul>
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="password">{__('Confirm with your password')}</Label>
                                            <Input
                                                id="password"
                                                name="password"
                                                type="password"
                                                className={errors.password ? 'border-destructive' : ''}
                                                placeholder={__('Enter your password')}
                                                required
                                            />
                                            {errors.password && <p className="text-sm text-destructive">{errors.password}</p>}
                                        </div>

                                        <div className="flex items-center gap-3">
                                            <Button type="submit" variant="destructive" disabled={processing}>
                                                {processing ? __('Scheduling...') : __('Schedule Account Deletion')}
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}

                {/* Cancel Deletion Confirmation Dialog */}
                <ConfirmationDialog
                    open={showCancelDialog}
                    onOpenChange={setShowCancelDialog}
                    onConfirm={handleCancelDeletion}
                    title={__('Cancel Account Deletion')}
                    description={__(
                        'Are you sure you want to cancel the account deletion? Your account will remain active and all scheduled deletion will be stopped.',
                    )}
                    confirmText={__('Yes, Cancel Deletion')}
                    cancelText={__('No, Keep Scheduled')}
                    variant="default"
                />
            </TabNavigation>
        </AdminLayout>
    );
}
