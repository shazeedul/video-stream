import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { AlertTriangle } from 'lucide-react';

interface DeleteConfirmationDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    itemName?: string;
    onConfirm: () => void;
    loading?: boolean;
    confirmText?: string;
    cancelText?: string;
}

export default function DeleteConfirmationDialog({
    open,
    onOpenChange,
    title,
    description,
    itemName,
    onConfirm,
    loading = false,
    confirmText = 'Delete',
    cancelText = 'Cancel',
}: DeleteConfirmationDialogProps) {
    const { __ } = useLocalizer();
    const handleConfirm = () => {
        onConfirm();
    };

    const handleCancel = () => {
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-destructive/10">
                            <AlertTriangle className="h-5 w-5 text-destructive" />
                        </div>
                        <div>
                            <DialogTitle className="text-left">{title}</DialogTitle>
                            <DialogDescription className="text-left">
                                {description}
                                {itemName && (
                                    <>
                                        {' '}
                                        <span className="font-medium text-foreground">"{itemName}"</span>?
                                    </>
                                )}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div className="rounded-lg border border-destructive/20 bg-destructive/5 p-4">
                    <p className="text-sm text-muted-foreground">
                        {__('This action cannot be undone. This will permanently delete the item and remove all associated data.')}
                    </p>
                </div>

                <DialogFooter className="gap-2 sm:gap-0">
                    <Button variant="outline" onClick={handleCancel} disabled={loading} className="mx-2 cursor-pointer">
                        {cancelText}
                    </Button>
                    <Button variant="destructive" onClick={handleConfirm} disabled={loading} className="cursor-pointer">
                        {loading ? (
                            <div className="flex items-center gap-2">
                                <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                {__('Deleting...')}
                            </div>
                        ) : (
                            confirmText
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
