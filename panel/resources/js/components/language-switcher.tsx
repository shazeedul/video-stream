import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { set } from '@/routes/admin/settings/preferences';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Can } from '@devwizard/laravel-react-permissions';
import { router, usePage } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { useCallback } from 'react';

interface LocaleConfig {
    label: string;
    flag: string;
    dir: string;
}

interface LocaleData {
    current: string;
    available: Record<string, LocaleConfig>;
}

export function LanguageSwitcher() {
    const { locale: localeData, auth } = usePage<{ locale: LocaleData; auth: { user: unknown } }>().props;
    const { __ } = useLocalizer();

    const currentLocale = localeData?.current || 'en';
    const availableLocales = localeData?.available || {};

    const handleLocaleChange = useCallback(
        (newLocale: string) => {
            if (newLocale === currentLocale) return;

            // Update user preference and reload to apply new locale
            router.patch(
                set('locale').url,
                { value: newLocale },
                {
                    preserveScroll: true,
                    preserveState: false,
                },
            );
        },
        [currentLocale],
    );

    // Don't render if no locales available or not authenticated
    if (!availableLocales || Object.keys(availableLocales).length === 0 || !auth?.user) {
        return null;
    }

    return (
        <Can permission="profile.preferences">
            <DropdownMenu>
                <TooltipProvider delayDuration={0}>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="icon" className="group h-9 w-9">
                                    <Languages className="!size-5 opacity-80 group-hover:opacity-100" />
                                    <span className="sr-only">{__('Switch language')}</span>
                                </Button>
                            </DropdownMenuTrigger>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>{__('Switch language')}</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
                <DropdownMenuContent align="end" className="w-48">
                    <DropdownMenuLabel>{__('Select Language')}</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    {Object.entries(availableLocales).map(([code, config]) => (
                        <DropdownMenuItem key={code} onClick={() => handleLocaleChange(code)} className={code === currentLocale ? 'bg-accent' : ''}>
                            <span className="flex items-center gap-2">
                                <span className="text-lg">{config.flag}</span>
                                <span>{config.label}</span>
                                {code === currentLocale && <span className="ml-auto text-xs text-muted-foreground">✓</span>}
                            </span>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        </Can>
    );
}
