import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { overview } from '@/routes/portal/settings/organization';
import { switchMethod } from '@/routes/portal/tenants';
import { type SharedData } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Link, router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown, Settings } from 'lucide-react';

interface TenantItem {
    id: string;
    name: string;
    slug?: string;
    status?: string;
    role?: string;
}

export function TenantSwitcher() {
    const { __ } = useLocalizer();
    const page = usePage<SharedData>();
    const currentTenant = page.props.currentTenant as TenantItem | undefined;
    const userTenants =
        (page.props.userTenants as TenantItem[] | undefined) ||
        (page.props.auth?.user?.tenants as TenantItem[] | undefined) ||
        (currentTenant ? [currentTenant] : []);

    const handleSwitch = (tenantId: string) => {
        if (!tenantId || tenantId === currentTenant?.id) {
            return;
        }

        router.post(
            switchMethod({ tenant: tenantId }).url,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    className="flex h-9 items-center gap-2 px-2.5 text-sm font-medium hover:bg-accent focus-visible:ring-1"
                    aria-label={__('Select Organization')}
                >
                    <div className="flex size-6 items-center justify-center rounded-md bg-primary/10 text-primary">
                        <Building2 className="size-3.5" />
                    </div>
                    <span className="max-w-[130px] truncate md:max-w-[180px]">
                        {currentTenant?.name ?? __('Select Organization')}
                    </span>
                    <ChevronsUpDown className="size-3.5 opacity-60" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-60">
                <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                    {__('Organizations')}
                </DropdownMenuLabel>
                <div className="max-h-60 overflow-y-auto">
                    {userTenants.map((tenant) => {
                        const isActive = currentTenant?.id === tenant.id;
                        return (
                            <DropdownMenuItem
                                key={tenant.id}
                                onClick={() => handleSwitch(tenant.id)}
                                className="flex cursor-pointer items-center justify-between gap-2 py-2"
                            >
                                <div className="flex items-center gap-2 truncate">
                                    <Building2 className="size-4 shrink-0 text-muted-foreground" />
                                    <span className="truncate">{tenant.name}</span>
                                </div>
                                {isActive && <Check className="size-4 shrink-0 text-primary" />}
                            </DropdownMenuItem>
                        );
                    })}
                </div>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link
                        href={overview().url}
                        className="flex w-full cursor-pointer items-center gap-2 py-2"
                    >
                        <Settings className="size-4 text-muted-foreground" />
                        <span>{__('Organization Settings')}</span>
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default TenantSwitcher;
