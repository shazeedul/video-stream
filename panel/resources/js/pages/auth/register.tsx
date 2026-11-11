import RegisteredUserController from '@/actions/App/Http/Controllers/Auth/RegisteredUserController';
import { login } from '@/routes';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';

import InputError from '@/components/input-error';
import SocialLogin from '@/components/social-login';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

interface RegisterProps {
    readonly availableProviders: string[];
}

export default function Register({ availableProviders }: Readonly<RegisterProps>) {
    const { __ } = useLocalizer();

    return (
        <AuthLayout title={__('Create an account')} description={__('Enter your details below to create your account')}>
            <Head title={__('Register')} />

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

            <Form
                action={RegisteredUserController.store()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">{__('Name')}</Label>
                                <Input id="name" type="text" required autoFocus autoComplete="name" name="name" placeholder={__('Full name')} />
                                <InputError message={errors.name} className="mt-2" />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">{__('Email address')}</Label>
                                <Input id="email" type="email" required autoComplete="email" name="email" placeholder={__('email@example.com')} />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">{__('Password')}</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    required
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder={__('Password')}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">{__('Confirm password')}</Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    required
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder={__('Confirm password')}
                                />
                                <InputError message={errors.password_confirmation} />
                            </div>

                            <Button type="submit" className="mt-2 w-full">
                                {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                {__('Create account')}
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            {__('Already have an account?')} <TextLink href={login()}>{__('Log in')}</TextLink>
                        </div>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
