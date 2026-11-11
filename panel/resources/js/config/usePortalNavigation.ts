import { dashboard } from '@/routes/portal';
import { overview as organizationOverview } from '@/routes/portal/settings/organization';
import { index as organizationMembersIndex } from '@/routes/portal/settings/organization/members';
import { edit as editPassword } from '@/routes/portal/settings/password';
import { index as preferencesIndex } from '@/routes/portal/settings/preferences';
import { deleteMethod as deleteProfile, edit as editProfile, overview as profileOverview } from '@/routes/portal/settings/profile';
import { type NavGroup, type NavItem } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Building2, Edit, LayoutGrid, Lock, Settings as SettingsIcon, Trash2, User as UserIcon, Users } from 'lucide-react';
import { useMemo } from 'react';

export function usePortalMainNavigation() {
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
        ],
        [__],
    );

    const footerNavItems: NavItem[] = useMemo(() => [], []);

    return { navGroups, footerNavItems };
}

/**
 * Hook to get user settings navigation
 * Used in user settings pages (Profile, Password, Preferences)
 */
export function useSettingsNavigation(): NavItem[] {
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
                title: __('Organization'),
                href: '#',
                icon: Building2,
                items: [
                    {
                        title: __('Overview'),
                        href: organizationOverview(),
                        icon: Building2,
                        active_when: organizationOverview().url,
                    },
                    {
                        title: __('Members'),
                        href: organizationMembersIndex(),
                        icon: Users,
                        active_when: organizationMembersIndex().url + '*',
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

export const useUserMainNavigation = usePortalMainNavigation;

