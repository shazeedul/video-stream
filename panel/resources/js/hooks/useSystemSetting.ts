import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

interface SystemSettingValue {
    value: string;
    url: string | null;
}

interface SystemSettingsMap {
    [key: string]: SystemSettingValue;
}

/**
 * Hook to access public system settings from Inertia shared props.
 * Only non-encrypted, public settings are available here.
 *
 * @example
 * ```tsx
 * const { systemSetting, systemSettings } = useSystemSetting()
 *
 * // Get a single setting
 * const siteName = systemSetting('site.title', 'Default Site Name')
 *
 * // Get a file URL
 * const logo = systemSetting('app.logo', '/default-logo.png', true)
 *
 * // Get all public settings
 * const settings = systemSettings()
 * ```
 */
export function useSystemSetting() {
    const { systemSettings: settings = {} } = usePage<SharedData>().props;

    /**
     * Get a system setting by key with optional default value.
     *
     * @param key The setting key (e.g., 'site.title', 'social.facebook_url')
     * @param defaultValue The default value if the setting doesn't exist
     * @param file Whether to return the file URL instead of the file path
     */
    const systemSetting = (key: string, defaultValue: string = '', file: boolean = false): string => {
        const setting = settings[key];

        if (!setting) {
            return defaultValue;
        }

        // If file is true and URL exists, return the URL
        if (file && setting.url) {
            return setting.url;
        }

        // Otherwise, return the value
        return setting.value ?? defaultValue;
    };

    /**
     * Get all public system settings as a key-value map.
     */
    const systemSettings = (): SystemSettingsMap => {
        return settings;
    };

    /**
     * Check if a setting exists.
     */
    const hasSetting = (key: string): boolean => {
        return key in settings;
    };

    return {
        systemSetting,
        systemSettings,
        hasSetting,
    };
}
