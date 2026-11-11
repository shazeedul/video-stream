import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { type User } from '@/types';
import { Mail, Phone, Shield, User as UserIcon } from 'lucide-react';

export interface UserSettingsHeaderProps {
    user: User;
}

export function UserSettingsHeader({ user }: Readonly<UserSettingsHeaderProps>) {
    // Get roles from user object
    const roles = (user.roles as Array<{ id: number; name: string; display_name: string }>) || [];
    const phone = user.phone as string | null | undefined;
    const username = user.username as string | null | undefined;
    const avatar = user.profile_photo_url as string | null | undefined;

    return (
        <div className="flex items-start gap-4">
            {/* User Avatar - Round Shape */}
            <Avatar className="size-16 shrink-0">
                <AvatarImage src={avatar || undefined} alt={user.name} />
                <AvatarFallback className="text-xl">{user.name.charAt(0).toUpperCase()}</AvatarFallback>
            </Avatar>

            {/* User Info */}
            <div className="flex-1 space-y-2">
                <div className="flex items-center gap-2">
                    <h1 className="text-xl font-semibold">{user.name}</h1>
                    <Badge variant={user.is_active ? 'default' : 'secondary'} className="capitalize">
                        {user.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                </div>

                {/* User Details */}
                <div className="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                    <div className="flex items-center gap-1.5">
                        <Mail className="size-3.5" />
                        <span>{user.email}</span>
                    </div>

                    {phone && (
                        <div className="flex items-center gap-1.5">
                            <Phone className="size-3.5" />
                            <span>{phone}</span>
                        </div>
                    )}

                    {username && (
                        <div className="flex items-center gap-1.5">
                            <UserIcon className="size-3.5" />
                            <span>@{username}</span>
                        </div>
                    )}

                    {roles.length > 0 && (
                        <div className="flex items-center gap-1.5">
                            <Shield className="size-3.5" />
                            <div className="flex flex-wrap gap-1">
                                {roles.map((role, index) => (
                                    <span key={role.id}>
                                        <span className="font-medium capitalize">{role.display_name || role.name}</span>
                                        {index < roles.length - 1 && <span>, </span>}
                                    </span>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
