import AuthenticatedSessionController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import InputError from '@/components/input-error';
import SocialLogin from '@/components/social-login';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { register } from '@/routes';
import { request } from '@/routes/password';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';

interface LoginProps {
    readonly status?: string;
    readonly canResetPassword: boolean;
    readonly availableProviders: string[];
}

export default function Login({ status, canResetPassword, availableProviders }: Readonly<LoginProps>) {
    const { __ } = useLocalizer();

    return (
        <AuthLayout title={__('Log in to your account')} description={__('Enter your email and password below to log in')}>
            <Head title={__('Log in')} />

            {/* Social Login Buttons */}
            <SocialLogin className="mb-6" availableProviders={availableProviders} />

            {availableProviders.length > 0 && (
                <div className="relative mb-6">
                    <div className="absolute inset-0 flex items-center">
                        <span className="w-full border-t" />
                    </div>
                    <div className="relative flex justify-center text-xs uppercase">
                        <span className="bg-background px-2 text-muted-foreground">{__('Or continue with')}</span>
                    </div>
                </div>
            )}

            <Form action={AuthenticatedSessionController.store()} resetOnSuccess={['password']} className="flex flex-col gap-6">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">{__('Email address')}</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    autoComplete="email"
                                    placeholder={__('email@example.com')}
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">{__('Password')}</Label>
                                    {canResetPassword && (
                                        <TextLink href={request()} className="ml-auto text-sm">
                                            {__('Forgot password?')}
                                        </TextLink>
                                    )}
                                </div>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    autoComplete="current-password"
                                    placeholder={__('Password')}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox id="remember" name="remember" />
                                <Label htmlFor="remember">{__('Remember me')}</Label>
                            </div>

                            <Button type="submit" className="mt-4 w-full" disabled={processing}>
                                {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                {__('Log in')}
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            {__("Don't have an account?")} <TextLink href={register()}>{__('Sign up')}</TextLink>
                        </div>
                    </>
                )}
            </Form>

            {status && <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>}
        </AuthLayout>
    );
}
