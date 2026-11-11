import { useSystemSetting } from '@/hooks/useSystemSetting';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import type { HTMLAttributes } from 'react';
import AppLogoIcon from './app-logo-icon';

export default function AppLogo({ className, ...props }: HTMLAttributes<HTMLElement>) {
    const { systemSetting } = useSystemSetting();

    const logoDark = systemSetting('app.logo_light', '', true);
    const logoLight = systemSetting('app.logo_dark', '', true);
    const { name } = usePage<SharedData>().props;

    const appName = systemSetting('app.name', name);

    return logoDark || logoLight ? (
        <>
            {logoDark && <img src={logoDark} alt={appName} {...props} className={cn('hidden h-full w-auto object-contain dark:block', className)} />}
            {logoLight && (
                <img src={logoLight} alt={appName} {...props} className={cn('block h-full w-auto object-contain dark:hidden', className)} />
            )}
            {!logoLight && logoDark && (
                <img src={logoDark} alt={appName} {...props} className={cn('block h-full w-auto object-contain dark:hidden', className)} />
            )}
            {!logoDark && logoLight && (
                <img src={logoLight} alt={appName} {...props} className={cn('hidden h-full w-auto object-contain dark:block', className)} />
            )}
        </>
    ) : (
        <div className={cn('flex items-center', className)}>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md text-sidebar-primary-foreground">
                <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">{appName}</span>
            </div>
        </div>
    );
}
