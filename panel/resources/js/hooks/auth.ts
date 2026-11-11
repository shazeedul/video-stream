import { User } from '@/types';
import { usePage } from '@inertiajs/react';

interface AuthPageProps {
    auth: {
        user: User | null;
    };
    [key: string]: unknown;
}

export function useAuth() {
    const { auth } = usePage<AuthPageProps>().props;

    return {
        user: auth.user,
        isAuthenticated: !!auth.user,
    };
}
