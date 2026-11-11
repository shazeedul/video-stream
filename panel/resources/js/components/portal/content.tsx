import { SidebarInset } from '@/components/ui/sidebar';
import * as React from 'react';

interface ContentProps extends React.ComponentProps<'main'> {
    variant?: 'header' | 'sidebar';
}

export function Content({ variant = 'header', children, ...props }: ContentProps) {
    if (variant === 'sidebar') {
        return <SidebarInset {...props}>{children}</SidebarInset>;
    }

    return (
        <main className="mx-auto flex h-full w-full flex-1 flex-col gap-4 rounded-xl" {...props}>
            {children}
        </main>
    );
}
