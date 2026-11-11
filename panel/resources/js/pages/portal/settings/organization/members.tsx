import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useInitials } from '@/hooks/use-initials';
import SettingsLayout from '@/pages/portal/settings/layout';
import InviteMemberDialog from '@/pages/portal/settings/organization/partials/invite-member-dialog';
import { overview } from '@/routes/portal/settings/organization';
import {
    destroy as destroyMember,
    index as membersIndex,
    update as updateMember,
} from '@/routes/portal/settings/organization/members';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Head, router, usePage } from '@inertiajs/react';
import { Trash2, UserPlus, Users } from 'lucide-react';
import { useMemo, useState } from 'react';

interface MemberUser {
    id?: number;
    name?: string;
    email?: string;
    profile_photo_url?: string;
}

interface Member {
    id: string;
    user_id: number;
    role: string;
    role_label: string;
    status: string;
    status_label: string;
    created_at?: string;
    user?: MemberUser;
}

interface RoleOption {
    value: string;
    label: string;
}

interface Props {
    readonly members: Member[];
    readonly roles: RoleOption[];
}

export default function OrganizationMembers({ members, roles }: Props) {
    const { __ } = useLocalizer();
    const getInitials = useInitials();
    const page = usePage<SharedData>();
    const currentTenant = page.props.currentTenant;

    const [inviteOpen, setInviteOpen] = useState(false);
    const [memberToRemove, setMemberToRemove] = useState<Member | null>(null);
    const [isRemoving, setIsRemoving] = useState(false);

    const isOwnerOrAdmin =
        currentTenant?.role === 'owner' || currentTenant?.role === 'admin';

    const ownersCount = useMemo(
        () => members.filter((m) => m.role === 'owner').length,
        [members],
    );

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('Organization Settings'),
            href: overview().url,
        },
        {
            title: __('Members'),
            href: membersIndex().url,
        },
    ];

    const handleRoleChange = (membershipId: string, newRole: string) => {
        router.patch(
            updateMember({ membership: membershipId }).url,
            { role: newRole },
            { preserveScroll: true },
        );
    };

    const handleConfirmRemove = () => {
        if (!memberToRemove) return;

        setIsRemoving(true);
        router.delete(destroyMember({ membership: memberToRemove.id }).url, {
            preserveScroll: true,
            onFinish: () => {
                setIsRemoving(false);
                setMemberToRemove(null);
            },
        });
    };

    const getRoleBadgeVariant = (role: string) => {
        switch (role) {
            case 'owner':
                return 'default';
            case 'admin':
                return 'secondary';
            default:
                return 'outline';
        }
    };

    return (
        <SettingsLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Organization Members')} />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">{__('Organization Members')}</h2>
                        <p className="text-muted-foreground">
                            {__('Manage teammates and their access levels in your organization.')}
                        </p>
                    </div>

                    {isOwnerOrAdmin && (
                        <Button onClick={() => setInviteOpen(true)} className="gap-2 self-start sm:self-auto">
                            <UserPlus className="h-4 w-4" />
                            {__('Invite Member')}
                        </Button>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Users className="h-5 w-5 text-muted-foreground" />
                            <CardTitle className="text-lg">{__('All Members')}</CardTitle>
                        </div>
                        <CardDescription>
                            {__('A total of :count members belong to this organization.', {
                                count: members.length,
                            })}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{__('Member')}</TableHead>
                                    <TableHead>{__('Role')}</TableHead>
                                    <TableHead className="hidden md:table-cell">{__('Status')}</TableHead>
                                    <TableHead className="hidden lg:table-cell">{__('Joined')}</TableHead>
                                    {isOwnerOrAdmin && (
                                        <TableHead className="text-right">{__('Actions')}</TableHead>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {members.map((member) => {
                                    const isSoleOwner =
                                        member.role === 'owner' && ownersCount <= 1;
                                    const memberName =
                                        member.user?.name || __('Unknown User');
                                    const memberEmail = member.user?.email || '';
                                    const joinedDate = member.created_at
                                        ? new Date(member.created_at).toLocaleDateString()
                                        : '—';

                                    return (
                                        <TableRow key={member.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="h-9 w-9">
                                                        <AvatarImage
                                                            src={member.user?.profile_photo_url}
                                                            alt={memberName}
                                                        />
                                                        <AvatarFallback>
                                                            {getInitials(memberName)}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="flex flex-col">
                                                        <span className="font-medium">
                                                            {memberName}
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {memberEmail}
                                                        </span>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {isOwnerOrAdmin ? (
                                                    isSoleOwner ? (
                                                        <TooltipProvider>
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <div className="inline-block">
                                                                        <Badge
                                                                            variant={getRoleBadgeVariant(
                                                                                member.role,
                                                                            )}
                                                                            className="capitalize"
                                                                        >
                                                                            {member.role_label ||
                                                                                member.role}
                                                                        </Badge>
                                                                    </div>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    {__(
                                                                        'An organization must have at least one active owner.',
                                                                    )}
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                    ) : (
                                                        <Select
                                                            value={member.role}
                                                            onValueChange={(val) =>
                                                                handleRoleChange(member.id, val)
                                                            }
                                                        >
                                                            <SelectTrigger className="h-8 w-32 capitalize">
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {roles.map((r) => (
                                                                    <SelectItem
                                                                        key={r.value}
                                                                        value={r.value}
                                                                        className="capitalize"
                                                                    >
                                                                        {r.label}
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    )
                                                ) : (
                                                    <Badge
                                                        variant={getRoleBadgeVariant(member.role)}
                                                        className="capitalize"
                                                    >
                                                        {member.role_label || member.role}
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="hidden md:table-cell">
                                                <Badge variant="outline" className="capitalize">
                                                    {member.status_label || member.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="hidden lg:table-cell text-sm text-muted-foreground">
                                                {joinedDate}
                                            </TableCell>
                                            {isOwnerOrAdmin && (
                                                <TableCell className="text-right">
                                                    {isSoleOwner ? (
                                                        <TooltipProvider>
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <span>
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="icon"
                                                                            disabled
                                                                            className="h-8 w-8 text-muted-foreground"
                                                                        >
                                                                            <Trash2 className="h-4 w-4" />
                                                                            <span className="sr-only">
                                                                                {__('Remove member')}
                                                                            </span>
                                                                        </Button>
                                                                    </span>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    {__(
                                                                        'Cannot remove the only owner of the organization.',
                                                                    )}
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                    ) : (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-8 w-8 text-destructive hover:bg-destructive/10"
                                                            onClick={() => setMemberToRemove(member)}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                            <span className="sr-only">
                                                                {__('Remove member')}
                                                            </span>
                                                        </Button>
                                                    )}
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            {/* Invite Member Dialog */}
            <InviteMemberDialog
                open={inviteOpen}
                onOpenChange={setInviteOpen}
                roles={roles}
            />

            {/* Remove Member Confirmation Dialog */}
            <AlertDialog
                open={memberToRemove !== null}
                onOpenChange={(open) => !open && setMemberToRemove(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{__('Remove Member')}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {__(
                                'Are you sure you want to remove :name from this organization? They will lose access immediately.',
                                {
                                    name:
                                        memberToRemove?.user?.name ||
                                        memberToRemove?.user?.email ||
                                        __('this member'),
                                },
                            )}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isRemoving}>
                            {__('Cancel')}
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleConfirmRemove}
                            disabled={isRemoving}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            {isRemoving ? __('Removing...') : __('Remove Member')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </SettingsLayout>
    );
}
