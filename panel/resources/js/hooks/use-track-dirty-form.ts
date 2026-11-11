import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

/**
 * Record type for form data where values can be any type
 */
type FormDataType = Record<string, unknown>;

/**
 * Props for useTrackDirtyForm hook - Full mode with form state management
 */
interface UseTrackDirtyFormPropsWithData<T extends FormDataType> {
    /** Initial form data to track changes against */
    data: T;
    /** Optional callback when draft should be saved */
    onSaveDraft?: () => void;
    /** Custom prompt message to show on navigation */
    promptMessage?: string;
}

/**
 * Props for useTrackDirtyForm hook - Simple mode with external isDirty
 */
interface UseTrackDirtyFormPropsWithIsDirty {
    /** External isDirty boolean (when form state is managed elsewhere) */
    isDirty: boolean;
    /** Optional callback when draft should be saved */
    onSaveDraft?: () => void;
    /** Custom prompt message to show on navigation */
    promptMessage?: string;
}

type UseTrackDirtyFormProps<T extends FormDataType> = UseTrackDirtyFormPropsWithData<T> | UseTrackDirtyFormPropsWithIsDirty;

/**
 * Return type for useTrackDirtyForm hook - Full mode
 */
interface UseTrackDirtyFormReturnWithData<T extends FormDataType> {
    /** Whether form has unsaved changes */
    isDirty: boolean;
    /** Function to set the form data */
    setData: (newData: T) => void;
    /** Pause navigation guard (call before saving) */
    pauseGuard: () => void;
    /** Resume navigation guard (call if save fails) */
    resumeGuard: () => void;
    /** Current form data */
    data: T;
    /** Whether the confirmation dialog is open (for SPA navigation) */
    showDialog: boolean;
    /** Close the dialog and stay on page */
    handleStay: () => void;
    /** Close the dialog and proceed with navigation */
    handleLeave: () => void;
    /** The message to display in the dialog */
    message: string;
}

/**
 * Return type for useTrackDirtyForm hook - Simple mode
 */
interface UseTrackDirtyFormReturnSimple {
    /** Whether form has unsaved changes */
    isDirty: boolean;
    /** Pause navigation guard (call before saving) */
    pauseGuard: () => void;
    /** Resume navigation guard (call if save fails) */
    resumeGuard: () => void;
    /** Whether the confirmation dialog is open (for SPA navigation) */
    showDialog: boolean;
    /** Close the dialog and stay on page */
    handleStay: () => void;
    /** Close the dialog and proceed with navigation */
    handleLeave: () => void;
    /** The message to display in the dialog */
    message: string;
}

/**
 * Deep equality comparison of two values
 */
const isDeepEqual = (first: unknown, second: unknown): boolean => {
    if (first === second) return true;
    if (first == null || second == null) return false;

    if (typeof first === 'object' && typeof second === 'object') {
        const firstKeys = Object.keys(first as object);
        const secondKeys = Object.keys(second as object);
        if (firstKeys.length !== secondKeys.length) return false;
        return firstKeys.every((key) => isDeepEqual((first as Record<string, unknown>)[key], (second as Record<string, unknown>)[key]));
    }

    return false;
};

const DEFAULT_MESSAGE = 'You have unsaved changes. Are you sure you want to leave?';

/**
 * Hook to track form changes and prompt before navigation with unsaved changes.
 * Combines dirty tracking with navigation guards for both browser and SPA navigation.
 *
 * @example Full mode - with form state management
 * ```tsx
 * const {
 *   isDirty,
 *   data: formData,
 *   setData,
 *   pauseGuard,
 *   showDialog,
 *   handleStay,
 *   handleLeave,
 *   message
 * } = useTrackDirtyForm({
 *   data: { title: form.title, settings: form.settings },
 *   promptMessage: __('You have unsaved changes'),
 * });
 * ```
 *
 * @example Simple mode - with external isDirty (e.g., when using Inertia's useForm)
 * ```tsx
 * const isDirty = JSON.stringify(initialData) !== JSON.stringify(data);
 * const { pauseDirty, showDialog, handleStay, handleLeave, message } = useTrackDirtyForm({
 *   isDirty,
 *   promptMessage: __('You have unsaved changes'),
 * });
 * ```
 */
export function useTrackDirtyForm<T extends FormDataType>(props: UseTrackDirtyFormPropsWithData<T>): UseTrackDirtyFormReturnWithData<T>;
export function useTrackDirtyForm(props: UseTrackDirtyFormPropsWithIsDirty): UseTrackDirtyFormReturnSimple;
export function useTrackDirtyForm<T extends FormDataType>(
    props: UseTrackDirtyFormProps<T>,
): UseTrackDirtyFormReturnWithData<T> | UseTrackDirtyFormReturnSimple {
    const isSimpleMode = 'isDirty' in props;
    const promptMessage = props.promptMessage ?? DEFAULT_MESSAGE;
    const onSaveDraft = props.onSaveDraft;

    // Form state (only used in full mode)
    // Memoize initialData to prevent dependency changes on every render
    const initialData = useMemo(() => (isSimpleMode ? ({} as T) : (props as UseTrackDirtyFormPropsWithData<T>).data), [isSimpleMode, props]);
    const [formState, setFormState] = useState<T>(initialData);

    // Dirty state
    const externalIsDirty = isSimpleMode ? (props as UseTrackDirtyFormPropsWithIsDirty).isDirty : false;
    const [internalIsDirty, setInternalIsDirty] = useState(false);
    const isDirty = isSimpleMode ? externalIsDirty : internalIsDirty;

    const isSubmitting = useRef(false);
    const [showDialog, setShowDialog] = useState(false);
    const [pendingUrl, setPendingUrl] = useState<string | null>(null);

    // Track dirty state (only in full mode)
    useEffect(() => {
        if (!isSimpleMode) {
            setInternalIsDirty(!isDeepEqual(initialData, formState));
        }
    }, [isSimpleMode, initialData, formState]);

    const setData = useCallback(
        (newData: T) => {
            if (!isSimpleMode) {
                setFormState(newData);
                setInternalIsDirty(!isDeepEqual(newData, initialData));
            }
        },
        [isSimpleMode, initialData],
    );

    // Handle browser navigation (refresh, close tab, back button)
    useEffect(() => {
        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            if (isDirty && !isSubmitting.current) {
                event.preventDefault();
                event.returnValue = promptMessage;
                onSaveDraft?.();
            }
        };

        globalThis.addEventListener('beforeunload', handleBeforeUnload);
        return () => globalThis.removeEventListener('beforeunload', handleBeforeUnload);
    }, [isDirty, onSaveDraft, promptMessage]);

    // Handle Inertia SPA navigation - show custom dialog
    useEffect(() => {
        const handleBeforeNavigation = (event: {
            preventDefault: () => void;
            detail: { visit: { url: URL; method: string; prefetch?: boolean } };
        }) => {
            if (!isDirty || isSubmitting.current) return;

            // Ignore prefetch requests (triggered on hover)
            if (event.detail.visit.prefetch) return;

            // Only intercept GET requests (navigation), not POST/PUT/DELETE (form submissions)
            if (event.detail.visit.method && event.detail.visit.method.toUpperCase() !== 'GET') return;

            // Prevent navigation and show our custom dialog
            event.preventDefault();
            setPendingUrl(event.detail.visit.url.href);
            setShowDialog(true);
        };

        const cleanup = router.on('before', handleBeforeNavigation);
        return cleanup;
    }, [isDirty]);

    const handleStay = useCallback(() => {
        setShowDialog(false);
        setPendingUrl(null);
    }, []);

    const handleLeave = useCallback(() => {
        setShowDialog(false);
        isSubmitting.current = true;
        onSaveDraft?.();

        if (pendingUrl) {
            router.visit(pendingUrl);
        }

        setPendingUrl(null);
    }, [pendingUrl, onSaveDraft]);

    const pauseGuard = useCallback(() => {
        isSubmitting.current = true;
    }, []);

    const resumeGuard = useCallback(() => {
        isSubmitting.current = false;
    }, []);

    // Return based on mode
    if (isSimpleMode) {
        return {
            isDirty,
            pauseGuard,
            resumeGuard,
            showDialog,
            handleStay,
            handleLeave,
            message: promptMessage,
        };
    }

    return {
        isDirty,
        data: formState,
        setData,
        pauseGuard,
        resumeGuard,
        showDialog,
        handleStay,
        handleLeave,
        message: promptMessage,
    };
}
