import ProfileController from '@/actions/App/Http/Controllers/Portal/Settings/ProfileController';
import ImageCropModal from '@/components/image-crop-modal';
import { UserSettingsHeader } from '@/components/shared/settings-header';
import UnlinkSocialModal from '@/components/unlink-social-modal';
import { send } from '@/routes/verification';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Transition } from '@headlessui/react';
import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import TabNavigation from '@/components/tab-navigation';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useSettingsNavigation } from '@/config/usePortalNavigation';
import PortalLayout from '@/layouts/portal-layout';
import { edit } from '@/routes/portal/settings/profile';
import { Camera, Upload } from 'lucide-react';

interface User {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    bio: string | null;
    company: string | null;
    designation: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    zip_code: string | null;
    social_links: { [key: string]: string };
    profile_photo_path: string | null;
    profile_photo_url: string;
    is_active: boolean;
    is_social_only: boolean;
    has_password: boolean;
    social_accounts: Array<{
        id: number;
        provider: string;
        name: string;
        email: string;
        avatar: string;
        username: string;
    }>;
}

interface ProfileProps {
    mustVerifyEmail: boolean;
    status?: string;
    user: User;
}

const providerLabels: Record<string, string> = {
    google: 'Google',
    github: 'GitHub',
    facebook: 'Facebook',
    twitter: 'X (Twitter)',
    linkedin: 'LinkedIn',
};

export default function Profile({ mustVerifyEmail, status, user }: Readonly<ProfileProps>) {
    const { auth } = usePage<SharedData>().props;
    const { __ } = useLocalizer();
    const [isCropModalOpen, setIsCropModalOpen] = useState(false);
    const [selectedImageForCrop, setSelectedImageForCrop] = useState<string | null>(null);
    const [croppedImage, setCroppedImage] = useState<string | null>(null);
    const [isUnlinkModalOpen, setIsUnlinkModalOpen] = useState(false);
    const [selectedSocialAccount, setSelectedSocialAccount] = useState<User['social_accounts'][0] | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('Profile settings'),
            href: edit().url,
        },
    ];

    const settingsNavigation = useSettingsNavigation();

    const handleCameraClick = () => {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = (e) => {
            const file = (e.target as HTMLInputElement).files?.[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    const imageDataUrl = event.target?.result as string;
                    setSelectedImageForCrop(imageDataUrl);
                    setIsCropModalOpen(true);
                };
                reader.readAsDataURL(file);
            }
        };
        input.click();
    };

    const handleCropComplete = (croppedImageData: string) => {
        setCroppedImage(croppedImageData);
        setSelectedImageForCrop(null);
    };

    const handleCropModalClose = () => {
        setIsCropModalOpen(false);
        setSelectedImageForCrop(null);
    };

    const unlinkProvider = (socialAccount: User['social_accounts'][0]) => {
        setSelectedSocialAccount(socialAccount);
        setIsUnlinkModalOpen(true);
    };

    const handleUnlinkModalClose = () => {
        setIsUnlinkModalOpen(false);
        setSelectedSocialAccount(null);
    };

    return (
        <PortalLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Profile settings')} />

            <TabNavigation header={<UserSettingsHeader user={auth.user} />} navigation={settingsNavigation}>
                <div className="space-y-6">
                    {/* Profile Information Section */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{__('Profile Information')}</CardTitle>
                            <CardDescription>{__('Update your name and email address')}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...ProfileController.update.form()}
                                options={{
                                    preserveScroll: true,
                                }}
                                onSuccess={() => {
                                    setCroppedImage(null);
                                    router.reload({ only: ['user'] });
                                }}
                                className="space-y-6"
                            >
                                {({ processing, recentlySuccessful, errors }) => (
                                    <>
                                        {croppedImage && <input type="hidden" name="cropped_image" value={croppedImage} />}

                                        {/* Profile Photo Section */}
                                        <div className="flex items-start gap-6">
                                            <div className="relative">
                                                {croppedImage ? (
                                                    <img
                                                        src={croppedImage}
                                                        alt="Profile photo preview"
                                                        className="h-24 w-24 rounded-full object-cover"
                                                    />
                                                ) : user.profile_photo_url ? (
                                                    <img
                                                        src={user.profile_photo_url}
                                                        alt={user.name}
                                                        className="h-24 w-24 rounded-full object-cover"
                                                    />
                                                ) : (
                                                    <div className="flex h-24 w-24 items-center justify-center rounded-full bg-muted">
                                                        <Camera className="h-8 w-8 text-muted-foreground" />
                                                    </div>
                                                )}
                                            </div>
                                            <div className="flex-1 space-y-3">
                                                <div>
                                                    <Label>{__('Profile Photo')}</Label>
                                                    <p className="text-sm text-muted-foreground">{__('Upload a new profile photo')}</p>
                                                </div>
                                                <div className="flex gap-2">
                                                    <Button type="button" variant="outline" onClick={handleCameraClick}>
                                                        <Upload className="mr-2 h-4 w-4" />
                                                        {__('Choose Photo')}
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="name">{__('Name')}</Label>
                                            <Input
                                                id="name"
                                                className="mt-1 block w-full"
                                                defaultValue={user.name}
                                                name="name"
                                                required
                                                autoComplete="name"
                                                placeholder={__('Full name')}
                                            />
                                            <InputError className="mt-2" message={errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="email">{__('Email address')}</Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                className="mt-1 block w-full"
                                                defaultValue={user.email}
                                                name="email"
                                                required
                                                autoComplete="email"
                                                placeholder={__('Email address')}
                                            />
                                            <InputError className="mt-2" message={errors.email} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="phone">{__('Phone Number')}</Label>
                                            <Input
                                                id="phone"
                                                type="tel"
                                                className="mt-1 block w-full"
                                                defaultValue={user.phone || ''}
                                                name="phone"
                                                autoComplete="tel"
                                                placeholder={__('Phone number')}
                                            />
                                            <InputError className="mt-2" message={errors.phone} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="designation">{__('Designation')}</Label>
                                            <Input
                                                id="designation"
                                                className="mt-1 block w-full"
                                                defaultValue={user.designation || ''}
                                                name="designation"
                                                placeholder={__('Your designation or job title')}
                                            />
                                            <InputError className="mt-2" message={errors.designation} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="bio">{__('Bio')}</Label>
                                            <textarea
                                                id="bio"
                                                className="mt-1 block min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                                defaultValue={user.bio || ''}
                                                name="bio"
                                                placeholder={__('Tell visitors about yourself, your experience, and what makes you unique...')}
                                                rows={4}
                                            />
                                            <InputError className="mt-2" message={errors.bio} />
                                        </div>
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label htmlFor="company">{__('Company')}</Label>
                                                <Input
                                                    id="company"
                                                    className="mt-1 block w-full"
                                                    defaultValue={user.company || ''}
                                                    name="company"
                                                    placeholder={__('Company name')}
                                                />
                                                <InputError className="mt-2" message={errors.company} />
                                            </div>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="address">{__('Address')}</Label>
                                            <Input
                                                id="address"
                                                className="mt-1 block w-full"
                                                defaultValue={user.address || ''}
                                                name="address"
                                                placeholder={__('Street address')}
                                            />
                                            <InputError className="mt-2" message={errors.address} />
                                        </div>
                                        <div className="grid gap-4 md:grid-cols-3">
                                            <div className="grid gap-2">
                                                <Label htmlFor="city">{__('City')}</Label>
                                                <Input
                                                    id="city"
                                                    className="mt-1 block w-full"
                                                    defaultValue={user.city || ''}
                                                    name="city"
                                                    placeholder={__('City')}
                                                />
                                                <InputError className="mt-2" message={errors.city} />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="state">{__('State')}</Label>
                                                <Input
                                                    id="state"
                                                    className="mt-1 block w-full"
                                                    defaultValue={user.state || ''}
                                                    name="state"
                                                    placeholder={__('State')}
                                                />
                                                <InputError className="mt-2" message={errors.state} />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="zip_code">{__('ZIP Code')}</Label>
                                                <Input
                                                    id="zip_code"
                                                    className="mt-1 block w-full"
                                                    defaultValue={user.zip_code || ''}
                                                    name="zip_code"
                                                    placeholder={__('ZIP code')}
                                                />
                                                <InputError className="mt-2" message={errors.zip_code} />
                                            </div>
                                        </div>
                                        {/* Social Links Section */}
                                        <div className="space-y-4">
                                            <Label className="text-base font-semibold">{__('Social Media Links')}</Label>
                                            <div className="grid gap-4 md:grid-cols-2">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="social_links_facebook">{__('Facebook')}</Label>
                                                    <Input
                                                        id="social_links_facebook"
                                                        type="url"
                                                        className="mt-1 block w-full"
                                                        defaultValue={user.social_links?.facebook || ''}
                                                        name="social_links[facebook]"
                                                        placeholder="https://facebook.com/yourprofile"
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="social_links_twitter">{__('Twitter')}</Label>
                                                    <Input
                                                        id="social_links_twitter"
                                                        type="url"
                                                        className="mt-1 block w-full"
                                                        defaultValue={user.social_links?.twitter || ''}
                                                        name="social_links[twitter]"
                                                        placeholder="https://twitter.com/yourhandle"
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="social_links_linkedin">{__('LinkedIn')}</Label>
                                                    <Input
                                                        id="social_links_linkedin"
                                                        type="url"
                                                        className="mt-1 block w-full"
                                                        defaultValue={user.social_links?.linkedin || ''}
                                                        name="social_links[linkedin]"
                                                        placeholder="https://linkedin.com/in/yourprofile"
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="social_links_instagram">{__('Instagram')}</Label>
                                                    <Input
                                                        id="social_links_instagram"
                                                        type="url"
                                                        className="mt-1 block w-full"
                                                        defaultValue={user.social_links?.instagram || ''}
                                                        name="social_links[instagram]"
                                                        placeholder="https://instagram.com/yourhandle"
                                                    />
                                                </div>

                                                <div className="grid gap-2 md:col-span-2">
                                                    <Label htmlFor="social_links_website">{__('Website')}</Label>
                                                    <Input
                                                        id="social_links_website"
                                                        type="url"
                                                        className="mt-1 block w-full"
                                                        defaultValue={user.social_links?.website || ''}
                                                        name="social_links[website]"
                                                        placeholder="https://yourwebsite.com"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        {mustVerifyEmail && auth.user.email_verified_at === null && (
                                            <div>
                                                <p className="-mt-4 text-sm text-muted-foreground">
                                                    {__('Your email address is unverified.')}{' '}
                                                    <Link
                                                        href={send()}
                                                        as="button"
                                                        className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                                    >
                                                        {__('Click here to resend the verification email.')}
                                                    </Link>
                                                </p>

                                                {status === 'verification-link-sent' && (
                                                    <div className="mt-2 text-sm font-medium text-green-600">
                                                        {__('A new verification link has been sent to your email address.')}
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                        <div className="flex items-center gap-4">
                                            <Button disabled={processing}>{__('Save')}</Button>

                                            <Transition
                                                show={recentlySuccessful}
                                                enter="transition ease-in-out"
                                                enterFrom="opacity-0"
                                                leave="transition ease-in-out"
                                                leaveTo="opacity-0"
                                            >
                                                <p className="text-sm text-neutral-600">{__('Saved')}</p>
                                            </Transition>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    {/* Social Accounts Section */}
                    {user.social_accounts.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>{__('Connected Social Accounts')}</CardTitle>
                                <CardDescription>{__('Manage your connected social media accounts')}</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {user.social_accounts.map((provider) => (
                                    <div key={provider.id} className="flex items-center justify-between rounded-lg border p-4">
                                        <div className="flex items-center space-x-3">
                                            <Avatar className="h-10 w-10">
                                                <AvatarImage src={provider.avatar} />
                                                <AvatarFallback>
                                                    {providerLabels[provider.provider]?.[0] || provider.provider[0].toUpperCase()}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div>
                                                <div className="flex items-center space-x-2">
                                                    <span className="font-medium">{providerLabels[provider.provider] || provider.provider}</span>
                                                    <Badge variant="secondary" className="text-xs">
                                                        {__('Connected')}
                                                    </Badge>
                                                </div>
                                                <p className="text-sm text-muted-foreground">{provider.username || provider.email}</p>
                                            </div>
                                        </div>
                                        <Button variant="outline" size="sm" onClick={() => unlinkProvider(provider)}>
                                            {__('Unlink')}
                                        </Button>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    )}
                </div>
            </TabNavigation>

            {/* Image Crop Modal */}
            <ImageCropModal
                isOpen={isCropModalOpen}
                onClose={handleCropModalClose}
                selectedImage={selectedImageForCrop}
                onCropComplete={handleCropComplete}
            />

            {/* Unlink Social Modal */}
            <UnlinkSocialModal
                isOpen={isUnlinkModalOpen}
                onClose={handleUnlinkModalClose}
                socialAccount={selectedSocialAccount}
                userHasPassword={!user.is_social_only || user.has_password}
            />
        </PortalLayout>
    );
}
