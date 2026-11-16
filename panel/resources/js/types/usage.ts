export interface TenantUsageRecordItem {
    date: string;
    storage_bytes: number;
    egress_bytes: number;
    view_count: number;
    video_count: number;
    formatted_egress: string;
    formatted_storage: string;
}

export interface StorageSummary {
    used_bytes: number;
    quota_bytes: number;
    usage_percentage: number;
    formatted_used: string;
    formatted_quota: string;
    is_exceeded: boolean;
}

export interface AssetBreakdownItem {
    bytes: number;
    count: number;
    formatted: string;
    percentage: number;
}

export interface StorageBreakdown {
    original: AssetBreakdownItem;
    hls: AssetBreakdownItem;
    thumbnail: AssetBreakdownItem;
}

export interface UsageTrends {
    total_views_30d: number;
    total_egress_bytes_30d: number;
    formatted_egress_30d: string;
    records: TenantUsageRecordItem[];
}

export interface UsagePageProps {
    tenant: {
        id: string;
        name: string;
        slug: string;
    };
    storage: StorageSummary;
    quota: {
        used_bytes: number;
        quota_bytes: number;
        usage_percentage: number;
    };
    breakdown: StorageBreakdown;
    trends: UsageTrends;
    records: TenantUsageRecordItem[];
}
