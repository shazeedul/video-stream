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
import { useLocalizer } from '@devwizard/laravel-localizer-react';

interface DirtyGuardDialogProps {
    readonly open: boolean;
    readonly onStay: () => void;
    readonly onLeave: () => void;
    readonly message?: string;
}

/**
 * Reusable dialog component for dirty form guard.
 * Shows a confirmation dialog when user tries to navigate away with unsaved changes.
 *
 * @example
 * ```tsx
 * const { showDialog, handleStay, handleLeave, message } = useTrackDirtyForm({ isDirty });
 *
 * return (
 *   <>
 *     {/* Your form content *\/}
 *     <DirtyGuardDialog
 *       open={showDialog}
 *       onStay={handleStay}
 *       onLeave={handleLeave}
 *       message={message}
 *     />
 *   </>
 * );
 * ```
 */
export function DirtyGuardDialog({ open, onStay, onLeave, message }: DirtyGuardDialogProps) {
    const { __ } = useLocalizer();

    return (
        <AlertDialog open={open}>
            <AlertDialogContent className="sm:max-w-md">
                <AlertDialogHeader>
                    <AlertDialogTitle>{__('Unsaved Changes')}</AlertDialogTitle>
                    <AlertDialogDescription className="text-base">
                        {message || __('You have unsaved changes. Are you sure you want to leave?')}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter className="gap-3 sm:gap-3">
                    <AlertDialogCancel
                        onClick={onStay}
                        className="mt-0 border-gray-300 transition-all duration-200 hover:border-gray-400 hover:bg-gray-50 focus:ring-2 focus:ring-gray-300 focus:ring-offset-2"
                    >
                        {__('Stay on Page')}
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={onLeave}
                        className="bg-red-600 text-white shadow-sm transition-all duration-200 hover:bg-red-700 hover:shadow-md focus:ring-2 focus:ring-red-500 focus:ring-offset-2 active:bg-red-800 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {__('Leave Without Saving')}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
