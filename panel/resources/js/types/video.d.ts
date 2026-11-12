export type VideoStatus = 'draft' | 'uploading' | 'uploaded' | 'queued' | 'probing' | 'transcoding' | 'packaging' | 'ready' | 'failed';

export type VideoVisibility = 'private' | 'unlisted' | 'public';

export type AssetType = 'original' | 'master' | 'hls_playlist' | 'hls_segment' | 'thumbnail';

export type UploadSessionStatus = 'pending' | 'completed' | 'aborted' | 'expired';

export interface VideoAsset {
    id: string;
    video_id: string;
    type: AssetType | string;
    disk: string;
    object_key: string;
    size_bytes: number | null;
    mime_type: string | null;
    metadata?: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
}

export interface UploadSession {
    id: string;
    tenant_id: string;
    video_id: string;
    upload_id: string;
    disk: string;
    object_key: string;
    part_size: number;
    total_parts: number;
    status: UploadSessionStatus | string;
    expires_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface VideoUser {
    id: number;
    name: string;
    email: string;
}

export interface Video {
    id: string;
    tenant_id: string;
    user_id: number | null;
    title: string;
    description: string | null;
    visibility: VideoVisibility;
    status: VideoStatus;
    duration_seconds: number | null;
    thumbnail_path: string | null;
    metadata?: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
    user?: VideoUser;
    tenant?: {
        id: string;
        name: string;
        slug?: string;
    };
    assets?: VideoAsset[];
    upload_sessions?: UploadSession[];
    uploadSessions?: UploadSession[];
    latest_upload_session?: UploadSession | null;
}

export interface VideoStats {
    total: number;
    ready: number;
    processing: number;
    draft: number;
    failed: number;
}

export interface VideoFilters {
    search?: string | null;
    status?: string | null;
    visibility?: string | null;
}
