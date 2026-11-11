import { useSystemSetting } from '@/hooks/useSystemSetting';
import { cn } from '@/lib/utils';
import { HTMLAttributes } from 'react';

export default function AppLogoIcon({ className, ...props }: HTMLAttributes<HTMLElement>) {
    const { systemSetting } = useSystemSetting();

    const iconDark = systemSetting('app.icon_light', '', true);
    const iconLight = systemSetting('app.icon_dark', '', true);
    const appName = systemSetting('app.name', 'DevWizard');

    return iconDark || iconLight ? (
        <>
            {iconDark && <img src={iconDark} alt={appName} {...props} className={cn('hidden object-contain dark:block', className)} />}
            {iconLight && <img src={iconLight} alt={appName} {...props} className={cn('block object-contain dark:hidden', className)} />}
            {!iconLight && iconDark && <img src={iconDark} alt={appName} {...props} className={cn('block object-contain dark:hidden', className)} />}
            {!iconDark && iconLight && <img src={iconLight} alt={appName} {...props} className={cn('hidden object-contain dark:block', className)} />}
        </>
    ) : (
        <img src="/assets/images/icon-light.png" alt={appName} {...props} className={cn('hidden object-contain dark:block', className)} />
    );
}
