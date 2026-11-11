import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

export function FlashMessage() {
    const { flashMessages } = usePage<SharedData>().props;

    useEffect(() => {
        const timeoutId = setTimeout(() => {
            Object.entries(flashMessages).forEach(([type, message]) => {
                if (message && message.trim()) {
                    switch (type) {
                        case 'success':
                            toast.success(message);
                            break;
                        case 'error':
                            toast.error(message);
                            break;
                        case 'warning':
                            toast.warning(message);
                            break;
                        case 'info':
                            toast.info(message);
                            break;
                        default:
                            toast(message);
                    }
                }
            });
        }, 500);
        return () => clearTimeout(timeoutId);
    }, [flashMessages]);

    return null;
}
