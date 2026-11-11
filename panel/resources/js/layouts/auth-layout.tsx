import { FlashMessage } from '@/components/flash-message';
import { Toaster } from '@/components/ui/sonner';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({ children, title, description, ...props }: { children: React.ReactNode; title: string; description: string }) {
    return (
        <AuthLayoutTemplate title={title} description={description} {...props}>
            {children}
            <FlashMessage />
            <Toaster />
        </AuthLayoutTemplate>
    );
}
