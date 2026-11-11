import { dashboard } from '@/routes/admin';
import { index as rolesIndex } from '@/routes/admin/roles';
import { edit as editPassword } from '@/routes/admin/settings/password';
import { index as preferencesIndex } from '@/routes/admin/settings/preferences';
import { deleteMethod as deleteProfile, edit as editProfile, overview as profileOverview } from '@/routes/admin/settings/profile';
import { index as settingsIndex } from '@/routes/admin/system-settings';
import { index as usersIndex } from '@/routes/admin/users';
import { type NavGroup, type NavItem } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Cog, Edit, LayoutGrid, Lock, Settings, Settings as SettingsIcon, Shield, Trash2, User as UserIcon, Users } from 'lucide-react';
import { useMemo } from 'react';

export function useAdminMainNavigation() {
    const { __ } = useLocalizer();

    const navGroups: NavGroup[] = useMemo(
        () => [
            {
                title: '',
                items: [
                    {
                        title: __('Dashboard'),
                        href: dashboard(),
                        icon: LayoutGrid,
                        permission: '*',
                        active_when: dashboard().url,
                    },
                ],
            },
            {
                title: __('Administration'),
                items: [
                    {
                        title: __('System'),
                        href: '#',
                        icon: Settings,
                        items: [
                            {
                                title: __('Roles'),
                                href: rolesIndex(),
                                icon: Shield,
                                permission: 'roles.view',
                                active_when: rolesIndex().url + '*',
                            },
                            {
                                title: __('Users'),
                                href: usersIndex(),
                                icon: Users,
                                permission: 'users.view',
                                active_when: usersIndex().url + '*',
                            },
                            {
                                title: __('Settings'),
                                href: settingsIndex(),
                                icon: Cog,
                                permission: 'system-settings.view',
                                active_when: settingsIndex().url + '*',
                            },
                        ],
                    },
                ],
            },
        ],
        [__],
    );

    const footerNavItems: NavItem[] = useMemo(() => [], []);

    return { navGroups, footerNavItems };
}

export function useAdminSettingsNavigation(): NavItem[] {
    const { __ } = useLocalizer();

    const settingsNavigation: NavItem[] = useMemo(
        () => [
            {
                title: __('General'),
                href: '#',
                icon: UserIcon,
                items: [
                    {
                        title: __('Profile'),
                        href: profileOverview(),
                        icon: UserIcon,
                        permission: 'profile.view',
                        active_when: profileOverview().url,
                    },
                    {
                        title: __('Edit Profile'),
                        href: editProfile(),
                        icon: Edit,
                        permission: 'profile.edit',
                        active_when: editProfile().url,
                    },
                    {
                        title: __('Password'),
                        href: editPassword(),
                        icon: Lock,
                        permission: 'profile.password.change',
                        active_when: editPassword().url,
                    },
                    {
                        title: __('Delete Account'),
                        href: deleteProfile(),
                        icon: Trash2,
                        permission: 'profile.delete',
                        active_when: deleteProfile().url,
                    },
                ],
            },
            {
                title: __('Preferences'),
                href: preferencesIndex(),
                icon: SettingsIcon,
                permission: 'profile.preferences',
                active_when: preferencesIndex().url,
            },
        ],
        [__],
    );

    return settingsNavigation;
}
