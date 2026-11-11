import { AdminInfo } from '@/components/admin/admin-info';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { edit } from '@/routes/admin/settings/profile';
import { type User } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Can } from '@devwizard/laravel-react-permissions';
import { Link, router } from '@inertiajs/react';
import { LogOut, Settings } from 'lucide-react';

interface MenuContentProps {
    user: User;
}

export function MenuContent({ user }: MenuContentProps) {
    const { __ } = useLocalizer();
    const cleanup = useMobileNavigation();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <AdminInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <Can permission="profile.view">
                    <DropdownMenuItem asChild>
                        <Link className="block w-full cursor-pointer" href={edit()} as="button" prefetch onClick={cleanup}>
                            <Settings className="mr-2" />
                            {__('Settings')}
                        </Link>
                    </DropdownMenuItem>
                </Can>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link className="block w-full cursor-pointer" href={logout()} as="button" onClick={handleLogout}>
                    <LogOut className="mr-2" />
                    {__('Log out')}
                </Link>
            </DropdownMenuItem>
        </>
    );
}
