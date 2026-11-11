import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';
import { home } from '@/routes';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { router } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Home, Search, ServerCrash, ShieldAlert, Wrench } from 'lucide-react';

interface ErrorPageProps {
    status: number;
}

export default function ErrorPage({ status }: ErrorPageProps) {
    const { __ } = useLocalizer();
    const goBack = () => window.history.back();
    const goHome = () => router.visit(home().url);
    const refreshPage = () => window.location.reload();

    const getErrorConfig = (status: number) => {
        switch (status) {
            case 404:
                return {
                    title: __('404: Page Not Found'),
                    description: __('Sorry, the page you are looking for could not be found.'),
                    icon: <Search className="h-16 w-16 text-blue-500" />,
                };
            case 403:
                return {
                    title: __('403: Forbidden'),
                    description: __('Sorry, you are forbidden from accessing this page.'),
                    icon: <ShieldAlert className="h-16 w-16 text-red-500" />,
                };
            case 500:
                return {
                    title: __('500: Server Error'),
                    description: __('Whoops, something went wrong on our servers.'),
                    icon: <ServerCrash className="h-16 w-16 text-orange-500" />,
                };
            case 503:
                return {
                    title: __('503: Service Unavailable'),
                    description: __('Sorry, we are doing some maintenance. Please check back soon.'),
                    icon: <Wrench className="h-16 w-16 text-yellow-500" />,
                };
            default:
                return {
                    title: __(':status: Error', { status }),
                    description: __('An unexpected error occurred.'),
                    icon: <AlertTriangle className="h-16 w-16 text-gray-500" />,
                };
        }
    };

    const config = getErrorConfig(status);

    return (
        <AuthLayout title={config.title} description={config.description}>
            <div className="space-y-6">
                {/* Error Icon */}
                <div className="flex justify-center">{config.icon}</div>

                {/* Action Buttons */}
                <div className="flex flex-col gap-3">
                    <Button onClick={goHome} variant="default" size="lg" className="w-full">
                        <Home className="mr-2 h-4 w-4" />
                        {__('Go Home')}
                    </Button>
                    <Button onClick={goBack} variant="outline" size="lg" className="w-full">
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        {__('Go Back')}
                    </Button>
                    {[500, 503].includes(status) && (
                        <Button onClick={refreshPage} variant="secondary" size="lg" className="w-full">
                            {__('Try Again')}
                        </Button>
                    )}
                </div>
            </div>
        </AuthLayout>
    );
}
