export interface TenantMembership {
    id: string;
    tenant_id: string;
    user_id: number;
    role: 'owner' | 'admin' | 'member' | 'viewer';
    status: 'active' | 'invited' | 'suspended';
    created_at?: string;
    updated_at?: string;
    user?: {
        id: number;
        name: string;
        email: string;
    };
}

export interface Tenant {
    id: string;
    name: string;
    slug: string;
    status: 'active' | 'suspended' | 'archived';
    settings?: Record<string, unknown> | null;
    users_count?: number;
    created_at: string;
    updated_at: string;
    users?: Array<{
        id: number;
        name: string;
        email: string;
        pivot: {
            id: string;
            role: 'owner' | 'admin' | 'member' | 'viewer';
            status: 'active' | 'invited' | 'suspended';
        };
    }>;
    memberships?: TenantMembership[];
}
