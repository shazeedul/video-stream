import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { AlertTriangle } from 'lucide-react';

interface ConfirmationDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    onConfirm: () => void;
    loading?: boolean;
    confirmText?: string;
    cancelText?: string;
    variant?: 'default' | 'destructive' | 'warning';
}

export default function ConfirmationDialog({
    open,
    onOpenChange,
    title,
    description,
    onConfirm,
    loading = false,
    confirmText = 'Confirm',
    cancelText = 'Cancel',
    variant = 'default',
}: ConfirmationDialogProps) {
    const handleConfirm = () => {
        onConfirm();
    };

    const handleCancel = () => {
        onOpenChange(false);
    };

    const iconColorClass =
        variant === 'destructive'
            ? 'bg-destructive/10 text-destructive'
            : variant === 'warning'
              ? 'bg-warning/10 text-warning'
              : 'bg-primary/10 text-primary';

    const buttonVariant = variant === 'destructive' ? 'destructive' : 'default';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex items-center gap-3">
                        <div className={`flex h-10 w-10 items-center justify-center rounded-full ${iconColorClass}`}>
                            <AlertTriangle className="h-5 w-5" />
                        </div>
                        <div>
                            <DialogTitle className="text-left">{title}</DialogTitle>
                            <DialogDescription className="text-left">{description}</DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <DialogFooter className="gap-2 sm:gap-0">
                    <Button variant="outline" onClick={handleCancel} disabled={loading} className="mx-2 cursor-pointer">
                        {cancelText}
                    </Button>
                    <Button variant={buttonVariant} onClick={handleConfirm} disabled={loading} className="cursor-pointer">
                        {loading ? (
                            <div className="flex items-center gap-2">
                                <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                Processing...
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
