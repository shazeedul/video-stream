import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { AnimatePresence, motion } from 'framer-motion';
import { AlertCircle, Loader2, RotateCcw, Save } from 'lucide-react';

type Alignment = 'bottom-center' | 'bottom-left' | 'bottom-right' | 'top-center' | 'top-left' | 'top-right';

interface UnsavedChangesAlertProps {
    /** Whether to show the alert */
    readonly show: boolean;
    /** Callback when reset button is clicked */
    readonly onReset: () => void;
    /** Callback when save button is clicked */
    readonly onSave: () => void;
    /** Whether the save operation is in progress */
    readonly saving?: boolean;
    /** Custom message to display */
    readonly message?: string;
    /** Position of the alert */
    readonly alignment?: Alignment;
    /** Additional class names */
    readonly className?: string;
    /** Form ID to submit (alternative to onSave callback) */
    readonly formId?: string;
    /** Vertical gap from edge in pixels */
    readonly verticalGap?: number;
    /** Horizontal gap from edge in pixels */
    readonly horizontalGap?: number;
}

const alignmentClasses: Record<Alignment, string> = {
    'bottom-center': 'bottom-0 left-1/2 -translate-x-1/2',
    'bottom-left': 'bottom-0 left-0',
    'bottom-right': 'bottom-0 right-0',
    'top-center': 'top-0 left-1/2 -translate-x-1/2',
    'top-left': 'top-0 left-0',
    'top-right': 'top-0 right-0',
};

const animationVariants = {
    'bottom-center': { initial: { y: 100, opacity: 0 }, animate: { y: 0, opacity: 1 }, exit: { y: 100, opacity: 0 } },
    'bottom-left': { initial: { x: -100, opacity: 0 }, animate: { x: 0, opacity: 1 }, exit: { x: -100, opacity: 0 } },
    'bottom-right': { initial: { x: 100, opacity: 0 }, animate: { x: 0, opacity: 1 }, exit: { x: 100, opacity: 0 } },
    'top-center': { initial: { y: -100, opacity: 0 }, animate: { y: 0, opacity: 1 }, exit: { y: -100, opacity: 0 } },
    'top-left': { initial: { x: -100, opacity: 0 }, animate: { x: 0, opacity: 1 }, exit: { x: -100, opacity: 0 } },
    'top-right': { initial: { x: 100, opacity: 0 }, animate: { x: 0, opacity: 1 }, exit: { x: 100, opacity: 0 } },
};

/**
 * A floating toast-style alert that appears when there are unsaved changes.
 * Provides Reset and Save buttons for easy form management.
 *
 * @example
 * ```tsx
 * const { isDirty, resetTo } = useTrackDirtyForm({
 *   initialData: form.settings,
 *   currentData: settings,
 * });
 *
 * <UnsavedChangesAlert
 *   show={isDirty}
 *   onReset={() => resetTo(form.settings)}
 *   onSave={handleSave}
 *   saving={processing}
 * />
 * ```
 */
export function UnsavedChangesAlert({
    show,
    onReset,
    onSave,
    saving = false,
    message,
    alignment = 'bottom-center',
    className,
    formId,
    verticalGap = 24,
    horizontalGap = 24,
}: UnsavedChangesAlertProps) {
    const { __ } = useLocalizer();

    const positionStyle: React.CSSProperties = {
        ...(alignment.includes('bottom') ? { marginBottom: verticalGap } : { marginTop: verticalGap }),
        ...(alignment.includes('left') ? { marginLeft: horizontalGap } : {}),
        ...(alignment.includes('right') ? { marginRight: horizontalGap } : {}),
    };

    const variants = animationVariants[alignment];

    return (
        <AnimatePresence>
            {show && (
                <motion.div
                    initial={variants.initial}
                    animate={variants.animate}
                    exit={variants.exit}
                    transition={{ type: 'spring', stiffness: 500, damping: 30 }}
                    className={cn('fixed z-50', alignmentClasses[alignment])}
                    style={positionStyle}
                >
                    <div className={cn('flex items-center gap-4 rounded-full bg-gray-900 px-4 py-2.5 shadow-lg dark:bg-gray-800', className)}>
                        <div className="flex items-center gap-2">
                            <AlertCircle className="h-4 w-4 text-yellow-500" />
                            <span className="text-sm font-medium text-white">{message ?? __('Unsaved changes')}</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={onReset}
                                disabled={saving}
                                className="h-7 rounded-full bg-gray-700 px-3 text-xs text-white hover:bg-gray-600 hover:text-white"
                            >
                                <RotateCcw className="mr-1.5 h-3 w-3" />
                                {__('Reset')}
                            </Button>
                            <Button
                                type={formId ? 'submit' : 'button'}
                                form={formId}
                                size="sm"
                                onClick={formId ? undefined : onSave}
                                disabled={saving}
                                className="h-7 rounded-full bg-primary px-3 text-xs hover:bg-primary/90"
                            >
                                {saving ? (
                                    <>
                                        <Loader2 className="mr-1.5 h-3 w-3 animate-spin" />
                                        {__('Saving...')}
                                    </>
                                ) : (
                                    <>
                                        <Save className="mr-1.5 h-3 w-3" />
                                        {__('Save')}
                                    </>
                                )}
                            </Button>
                        </div>
                    </div>
                </motion.div>
            )}
        </AnimatePresence>
    );
}
