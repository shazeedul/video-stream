import { suspend } from '@/actions/App/Http/Controllers/Admin/UserController';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { type User } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { useForm } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';

interface Props {
    user: User | null;
    open: boolean;
    onClose: () => void;
}

export default function SuspendUserSheet({ user, open, onClose }: Props) {
    const { __ } = useLocalizer();
    const { data, setData, post, processing, errors, reset } = useForm({
        reason: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!user) return;

        post(suspend({ user: user.id }).url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    const handleClose = () => {
        reset();
        onClose();
    };

    return (
        <Sheet open={open} onOpenChange={handleClose}>
            <SheetContent>
                <SheetHeader className="border-b">
                    <SheetTitle>{__('Suspend User')}</SheetTitle>
                    <SheetDescription>
                        {__('Provide a reason for suspending')} {user?.name}. {__('The user will be logged out and unable to access the platform.')}
                    </SheetDescription>
                </SheetHeader>

                <form onSubmit={handleSubmit} className="flex-1 space-y-6 px-4 py-2">
                    {/* Warning */}
                    <div className="rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-800 dark:bg-orange-950/20">
                        <div className="flex gap-3">
                            <AlertCircle className="h-5 w-5 flex-shrink-0 text-orange-500" />
                            <div className="flex-1">
                                <h4 className="font-medium text-orange-900 dark:text-orange-200">{__('This action will suspend the user')}</h4>
                                <p className="mt-1 text-sm text-orange-700 dark:text-orange-300">
                                    {__(
                                        'The user will immediately lose access to their account and will see the suspension reason when they try to log in.',
                                    )}
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Reason Input */}
                    <div className="space-y-2">
                        <label htmlFor="reason" className="text-sm font-medium">
                            {__('Suspension Reason')} <span className="text-destructive">*</span>
                        </label>
                        <Textarea
                            id="reason"
                            placeholder={__('Enter the reason for suspending this user...')}
                            value={data.reason}
                            onChange={(e) => setData('reason', e.target.value)}
                            rows={5}
                            className={errors.reason ? 'border-destructive' : ''}
                        />
                        {errors.reason && <p className="text-sm text-destructive">{errors.reason}</p>}
                    </div>

                    {/* Actions */}
                    <div className="flex gap-3">
                        <Button type="button" variant="outline" onClick={handleClose} className="flex-1">
                            {__('Cancel')}
                        </Button>
                        <Button type="submit" variant="destructive" disabled={processing || !data.reason.trim()} className="flex-1">
                            {processing ? __('Suspending...') : __('Suspend User')}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}
