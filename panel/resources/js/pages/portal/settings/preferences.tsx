import AppearanceToggleTab from '@/components/appearance-tabs';
import HeadingSmall from '@/components/heading-small';
import { UserSettingsHeader } from '@/components/shared/settings-header';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';

import TabNavigation from '@/components/tab-navigation';
import { useSettingsNavigation } from '@/config/usePortalNavigation';
import PortalLayout from '@/layouts/portal-layout';
import { index, set } from '@/routes/portal/settings/preferences';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Head, router, usePage } from '@inertiajs/react';
import { useCallback } from 'react';

interface Preferences {
    appearance?: string;
    locale?: string;
    notifications?: {
        email?: boolean;
    };
}

interface LocaleConfig {
    label: string;
    flag: string;
    dir: string;
}

interface PreferencesPageProps {
    readonly preferences: Preferences;
    readonly availableLocales: Record<string, LocaleConfig>;
}

export default function PreferencesPage({ preferences, availableLocales }: PreferencesPageProps) {
    const { __ } = useLocalizer();
    const { auth } = usePage<SharedData>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('Preferences'),
            href: index().url,
        },
    ];

    const settingsNavigation = useSettingsNavigation();

    const updatePreference = useCallback((key: string, value: string | boolean | null) => {
        router.patch(
            set(key).url,
            { value },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    }, []);

    // Handle appearance change - update both appearance and database
    const handleAppearanceChange = useCallback(
        (appearance: string) => {
            updatePreference('appearance', appearance);
        },
        [updatePreference],
    );

    // Handle locale change - update preference and reload
    const handleLocaleChange = useCallback(
        (newLocale: string) => {
            updatePreference('locale', newLocale);

            // Reload the page to apply the new locale across the app
            router.reload();
        },
        [updatePreference],
    );

    return (
        <PortalLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Preferences')} />

            <TabNavigation header={<UserSettingsHeader user={auth.user} />} navigation={settingsNavigation}>
                <div className="space-y-6">
                    <HeadingSmall title={__('User Preferences')} description={__('Manage your application preferences and settings')} />

                    <div className="grid gap-6">
                        {/* Appearance Settings */}
                        <Card>
                            <CardContent className="space-y-6 pt-6">
                                <div className="space-y-2">
                                    <Label>{__('Appearance')}</Label>
                                    <p className="text-sm text-muted-foreground">{__('Select your preferred appearance for the application')}</p>
                                    <AppearanceToggleTab className="mt-3" onAppearanceChange={handleAppearanceChange} />
                                </div>

                                <Separator />

                                <div className="space-y-2">
                                    <Label htmlFor="locale">{__('Language')}</Label>
                                    <p className="text-sm text-muted-foreground">{__('Choose your preferred language')}</p>
                                    <Select defaultValue={preferences.locale || 'en'} onValueChange={handleLocaleChange}>
                                        <SelectTrigger className="mt-2">
                                            <SelectValue placeholder={__('Select language')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(availableLocales).map(([code, locale]) => (
                                                <SelectItem key={code} value={code}>
                                                    <span className="flex items-center gap-2">
                                                        <span>{locale.flag}</span>
                                                        <span>{locale.label}</span>
                                                    </span>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Notifications */}
                        <Card>
                            <CardHeader>
                                <CardTitle>{__('Notification Preferences')}</CardTitle>
                                <CardDescription>{__('Control how you receive notifications')}</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="flex items-center justify-between">
                                    <div className="space-y-0.5">
                                        <Label htmlFor="email-notifications">{__('Email Notifications')}</Label>
                                        <p className="text-sm text-muted-foreground">{__('Receive notifications via email')}</p>
                                    </div>
                                    <Switch
                                        id="email-notifications"
                                        defaultChecked={preferences.notifications?.email ?? true}
                                        onCheckedChange={(checked) => updatePreference('notifications.email', checked)}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </TabNavigation>
        </PortalLayout>
    );
}
