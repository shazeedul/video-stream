import { destroy as logout } from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { router } from '@inertiajs/react';
import { ShieldX } from 'lucide-react';

interface SuspendedPageProps {
    status_reason?: string;
    status_updated_at?: string;
}

export default function Suspended({ status_reason, status_updated_at }: SuspendedPageProps) {
    const { __ } = useLocalizer();
    const handleLogout = () => {
        router.post(logout().url);
    };

    return (
        <AuthLayout title={__('Account Suspended')} description={__('Your account has been suspended by an administrator.')}>
            <div className="space-y-6">
                {/* Suspension Icon */}
                <div className="flex justify-center">
                    <ShieldX className="h-16 w-16 text-red-500" />
                </div>

                {/* Suspension Information */}
                <div className="space-y-4">
                    <div className="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950/20">
                        <h3 className="mb-2 font-semibold text-red-900 dark:text-red-200">{__('Account Status')}</h3>
                        <p className="text-sm text-red-700 dark:text-red-300">
                            {__('Your account has been suspended and you no longer have access to the platform.')}
                        </p>
                    </div>

                    {status_reason && (
                        <div className="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50">
                            <h3 className="mb-2 font-semibold text-gray-900 dark:text-gray-200">{__('Reason for Suspension')}</h3>
                            <p className="text-sm text-gray-700 dark:text-gray-300">{status_reason}</p>
                        </div>
                    )}

                    {status_updated_at && (
                        <p className="text-center text-sm text-gray-600 dark:text-gray-400">
                            {__('Suspended on')}{' '}
                            {new Date(status_updated_at).toLocaleDateString('en-US', {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit',
                            })}
                        </p>
                    )}
                </div>

                {/* Contact Information */}
                <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950/20">
                    <p className="text-sm text-blue-700 dark:text-blue-300">
                        {__('If you believe this is a mistake or would like to appeal this decision, please contact our support team.')}
                    </p>
                </div>

                {/* Logout Button */}
                <div className="pt-2">
                    <Button onClick={handleLogout} variant="outline" size="lg" className="w-full">
                        {__('Logout')}
                    </Button>
                </div>
            </div>
        </AuthLayout>
    );
}
