import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User & {
        permissions: string[];
    };
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    is_active?: boolean;
    active_when?: string; // URL pattern to consider as active (supports wildcards)
    items?: NavItem[]; // Support for submenus
    permission?: string; // Permission(s) required to view this item (supports || and && operators)
}

export interface FlashMessages {
    success?: string;
    error?: string;
    warning?: string;
    info?: string;
}

export interface SystemSettingValue {
    value: string;
    url: string | null;
}

export interface NotificationData {
    title: string;
    message: string;
    action_url?: string;
    icon?: string;
    level: 'info' | 'success' | 'warning' | 'error';
}

export interface Notification {
    id: string;
    type: string;
    data: NotificationData;
    read_at: string | null;
    created_at: string;
}

export interface NotificationsData {
    unread_count: number;
    recent: Notification[];
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    flashMessages: FlashMessages;
    sidebarOpen: boolean;
    systemSettings: Record<string, SystemSettingValue>;
    [key: string]: unknown;
}

export interface Permission {
    id: number;
    name: string;
    display_name: string;
    group: string;
    guard_name: string;
    created_at: string;
    updated_at: string;
}

export interface Role {
    id: number;
    name: string;
    display_name: string;
    description?: string;
    guard_name: string;
    is_default: boolean;
    created_at: string;
    updated_at: string;
    permissions?: Permission[];
}

export interface Links {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginationProps {
    links: Links[];
    from: number | null;
    to: number | null;
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: Links[];
}

export interface SimplePaginationProps {
    current_page: number;
    from: number | null;
    to: number | null;
    per_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
    first_page_url: string | null;
    current_page_url: string;
    path: string;
}

// Base Model Interface
export interface BaseModel {
    id: number;
    created_at: string;
    updated_at: string;
}

// User Interface
export interface User extends BaseModel {
    name: string;
    email: string;
    profile_photo_url: string;
    email_verified_at: string | null;
    status: 'active' | 'suspended' | 'inactive';
    status_reason: string | null;
    status_updated_at: string | null;
    [key: string]: unknown;
}
