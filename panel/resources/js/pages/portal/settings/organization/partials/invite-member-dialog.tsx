import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store } from '@/routes/portal/settings/organization/members';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { useForm } from '@inertiajs/react';
import { type FormEventHandler, useEffect } from 'react';

interface RoleOption {
    value: string;
    label: string;
}

interface InviteMemberDialogProps {
    readonly open: boolean;
    readonly onOpenChange: (open: boolean) => void;
    readonly roles?: RoleOption[];
}

export function InviteMemberDialog({
    open,
    onOpenChange,
    roles = [
        { value: 'owner', label: 'Owner' },
        { value: 'admin', label: 'Admin' },
        { value: 'member', label: 'Member' },
        { value: 'viewer', label: 'Viewer' },
    ],
}: InviteMemberDialogProps) {
    const { __ } = useLocalizer();

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        email: '',
        role: 'member',
    });

    useEffect(() => {
        if (!open) {
            reset();
            clearErrors();
        }
    }, [open, reset, clearErrors]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store().url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{__('Invite Member')}</DialogTitle>
                    <DialogDescription>
                        {__('Add an existing user to your organization by their email address.')}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="member-email">{__('Email Address')}</Label>
                        <Input
                            id="member-email"
                            type="email"
                            placeholder="name@company.com"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                            autoFocus
                        />
                        {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="member-role">{__('Role')}</Label>
                        <Select
                            value={data.role}
                            onValueChange={(value) => setData('role', value)}
                        >
                            <SelectTrigger id="member-role" className="w-full">
                                <SelectValue placeholder={__('Select a role')} />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((role) => (
                                    <SelectItem key={role.value} value={role.value}>
                                        {role.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.role && <p className="text-sm text-destructive">{errors.role}</p>}
                    </div>

                    <DialogFooter className="gap-2 sm:gap-0">
                        <DialogClose asChild>
                            <Button type="button" variant="outline" disabled={processing}>
                                {__('Cancel')}
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            {processing ? __('Inviting...') : __('Invite Member')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default InviteMemberDialog;
