export interface WorkerInstance {
    id: string;
    hostname: string;
    age_seconds: number;
}

export interface ComponentHealth {
    status: 'ok' | 'error' | 'down' | 'degraded' | string;
    latency_ms?: number;
    error?: string;
    buckets?: string[];
    missing_buckets?: string[];
    queue_depth?: number;
    consumers?: number;
    active_count?: number;
    instances?: WorkerInstance[];
    [key: string]: unknown;
}

export interface HealthDiagnostics {
    status: 'healthy' | 'degraded' | 'unhealthy' | string;
    timestamp: string;
    components: {
        database: ComponentHealth;
        redis: ComponentHealth;
        minio: ComponentHealth;
        rabbitmq: ComponentHealth;
        workers: ComponentHealth;
        [key: string]: ComponentHealth;
    };
}

export interface TopTenantStorage {
    id: string;
    name: string;
    slug: string;
    storage_bytes: number;
    quota_bytes: number;
    usage_percentage: number;
    formatted_storage: string;
    formatted_quota: string;
}

export interface MonitoringPageProps {
    diagnostics: HealthDiagnostics;
    health: HealthDiagnostics;
    components: {
        database: ComponentHealth;
        redis: ComponentHealth;
        minio: ComponentHealth;
        rabbitmq: ComponentHealth;
        workers: ComponentHealth;
        [key: string]: ComponentHealth;
    };
    queue: ComponentHealth;
    workers: ComponentHealth;
    topTenants: TopTenantStorage[];
}
