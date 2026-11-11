import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Form } from '@inertiajs/react';
import { AlertTriangle, Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface SocialAccount {
    id: number;
    provider: string;
    name: string;
    email: string;
    avatar: string;
    username: string;
}

interface UnlinkSocialModalProps {
    isOpen: boolean;
    onClose: () => void;
    socialAccount: SocialAccount | null;
    userHasPassword: boolean;
}

const providerLabels: Record<string, string> = {
    google: 'Google',
    github: 'GitHub',
    facebook: 'Facebook',
    twitter: 'X (Twitter)',
    linkedin: 'LinkedIn',
};

export default function UnlinkSocialModal({ isOpen, onClose, socialAccount, userHasPassword }: UnlinkSocialModalProps) {
    const [showPassword, setShowPassword] = useState(false);
    const [showPasswordConfirmation, setShowPasswordConfirmation] = useState(false);
    const { __ } = useLocalizer();

    if (!socialAccount) return null;

    const providerName = providerLabels[socialAccount.provider] || socialAccount.provider;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center space-x-2">
                        <AlertTriangle className="h-5 w-5 text-amber-500" />
                        <span>{__('Unlink :provider Account', { provider: providerName })}</span>
                    </DialogTitle>
                    <DialogDescription>
                        {__('You are about to unlink your :provider account.', { provider: providerName })}
                        {!userHasPassword && __(' You will need to set a password to continue logging in with email after unlinking.')}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6">
                    {/* Social Account Info */}
                    <div className="flex items-center space-x-3 rounded-lg border p-3">
                        <Avatar className="h-10 w-10">
                            <AvatarImage src={socialAccount.avatar} />
                            <AvatarFallback>{providerName[0] || socialAccount.provider[0].toUpperCase()}</AvatarFallback>
                        </Avatar>
                        <div>
                            <p className="font-medium">{socialAccount.name}</p>
                            <p className="text-sm text-muted-foreground">{socialAccount.username || socialAccount.email}</p>
                        </div>
                    </div>

                    <Form action={`/auth/${socialAccount.provider}/unlink`} method="delete" onSuccess={onClose} className="space-y-4">
                        {({ processing, errors }) => (
                            <>
                                {!userHasPassword && (
                                    <>
                                        <div className="rounded-lg border border-amber-200 bg-amber-50 p-4">
                                            <div className="flex items-start space-x-2">
                                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                                                <div className="text-sm text-amber-700">
                                                    <p className="font-medium">{__('Password Required')}</p>
                                                    <p>{__('Since this is your only sign-in method, you must set a password before unlinking.')}</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="space-y-4">
                                            <div className="grid gap-2">
                                                <Label htmlFor="password">{__('New Password')}</Label>
                                                <div className="relative">
                                                    <Input
                                                        id="password"
                                                        type={showPassword ? 'text' : 'password'}
                                                        name="password"
                                                        required
                                                        autoComplete="new-password"
                                                        placeholder={__('Enter new password')}
                                                        className="pr-10"
                                                    />
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="absolute top-0 right-0 h-full px-3 py-2 hover:bg-transparent"
                                                        onClick={() => setShowPassword(!showPassword)}
                                                        tabIndex={-1}
                                                    >
                                                        {showPassword ? (
                                                            <EyeOff className="h-4 w-4 text-muted-foreground" />
                                                        ) : (
                                                            <Eye className="h-4 w-4 text-muted-foreground" />
                                                        )}
                                                    </Button>
                                                </div>
                                                <InputError message={errors.password} />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="password_confirmation">{__('Confirm Password')}</Label>
                                                <div className="relative">
                                                    <Input
                                                        id="password_confirmation"
                                                        type={showPasswordConfirmation ? 'text' : 'password'}
                                                        name="password_confirmation"
                                                        required
                                                        autoComplete="new-password"
                                                        placeholder={__('Confirm new password')}
                                                        className="pr-10"
                                                    />
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="absolute top-0 right-0 h-full px-3 py-2 hover:bg-transparent"
                                                        onClick={() => setShowPasswordConfirmation(!showPasswordConfirmation)}
                                                        tabIndex={-1}
                                                    >
                                                        {showPasswordConfirmation ? (
                                                            <EyeOff className="h-4 w-4 text-muted-foreground" />
                                                        ) : (
                                                            <Eye className="h-4 w-4 text-muted-foreground" />
                                                        )}
                                                    </Button>
                                                </div>
                                                <InputError message={errors.password_confirmation} />
                                            </div>
                                        </div>
                                    </>
                                )}

                                {userHasPassword && (
                                    <div className="rounded-lg border border-blue-200 bg-blue-50 p-4">
                                        <div className="flex items-start space-x-2">
                                            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
                                            <div className="text-sm text-blue-700">
                                                <p className="font-medium">{__('Account Security')}</p>
                                                <p>{__('You can continue logging in with your email and password after unlinking.')}</p>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                <div className="flex justify-end space-x-2 pt-4">
                                    <Button type="button" variant="outline" onClick={onClose}>
                                        {__('Cancel')}
                                    </Button>
                                    <Button type="submit" variant="destructive" disabled={processing}>
                                        {processing ? __('Unlinking...') : __('Unlink :provider', { provider: providerName })}
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </DialogContent>
        </Dialog>
    );
}
