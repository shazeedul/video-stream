import { store as storeVideoRoute } from '@/routes/portal/videos';
import {
    abort as uploadAbortRoute,
    complete as uploadCompleteRoute,
    initiate as uploadInitiateRoute,
    parts as uploadPartsRoute,
} from '@/routes/portal/videos/upload';
import type { Video, VideoVisibility } from '@/types/video';
import axios from 'axios';

export type UploadState = 'idle' | 'creating_draft' | 'initiating' | 'uploading' | 'completing' | 'completed' | 'aborted' | 'failed';

export interface UploadProgress {
    uploadedBytes: number;
    totalBytes: number;
    percentage: number;
    speedBytesPerSecond: number;
    speedFormatted: string;
    estimatedTimeRemainingSeconds: number | null;
    currentPart: number;
    totalParts: number;
}

export interface ChunkedUploaderOptions {
    file: File;
    videoId?: string;
    title?: string;
    description?: string;
    visibility?: VideoVisibility;
    concurrency?: number;
    onProgress?: (progress: UploadProgress) => void;
    onStateChange?: (state: UploadState) => void;
    onSuccess?: (result: { videoId: string; video?: Video; message?: string }) => void;
    onError?: (error: Error) => void;
}

export interface PartUploadResult {
    PartNumber: number;
    ETag: string;
}

export function formatBytes(bytes: number | null | undefined): string {
    if (bytes === null || bytes === undefined || bytes <= 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.min(Math.floor(Math.log(bytes) / Math.log(k)), sizes.length - 1);
    const val = (bytes / Math.pow(k, i)).toFixed(1);
    return `${val} ${sizes[i]}`;
}

export function formatSpeed(bytesPerSecond: number): string {
    if (bytesPerSecond <= 0) return '0 KB/s';
    if (bytesPerSecond >= 1024 * 1024 * 1024) {
        return `${(bytesPerSecond / (1024 * 1024 * 1024)).toFixed(1)} GB/s`;
    }
    if (bytesPerSecond >= 1024 * 1024) {
        return `${(bytesPerSecond / (1024 * 1024)).toFixed(1)} MB/s`;
    }
    return `${(bytesPerSecond / 1024).toFixed(0)} KB/s`;
}

export function resolveContentType(file: File): string {
    if (file.type && file.type.startsWith('video/')) {
        if (file.type === 'video/mkv') return 'video/x-matroska';
        return file.type;
    }
    const ext = file.name.split('.').pop()?.toLowerCase();
    switch (ext) {
        case 'mp4':
            return 'video/mp4';
        case 'mov':
            return 'video/quicktime';
        case 'mkv':
            return 'video/x-matroska';
        case 'webm':
            return 'video/webm';
        case 'avi':
            return 'video/x-msvideo';
        case 'm4v':
            return 'video/x-m4v';
        case 'flv':
            return 'video/x-flv';
        case 'ts':
            return 'video/mp2t';
        case 'ogv':
            return 'video/ogg';
        default:
            return 'video/mp4';
    }
}

export class ChunkedUploader {
    private readonly file: File;
    private readonly options: ChunkedUploaderOptions;
    private videoId: string | null = null;
    private uploadId: string | null = null;
    private partSize: number = 5 * 1024 * 1024;
    private totalParts: number = 1;
    private state: UploadState = 'idle';
    private isAborted: boolean = false;
    private readonly activeXhrs: Map<number, XMLHttpRequest> = new Map();
    private readonly partLoadedBytes: Map<number, number> = new Map();
    private readonly urlCache: Map<number, string> = new Map();
    private completedParts: PartUploadResult[] = [];
    private uploadStartTime: number = 0;

    constructor(options: ChunkedUploaderOptions) {
        this.file = options.file;
        this.options = options;
        if (options.videoId) {
            this.videoId = options.videoId;
        }
    }

    public getState(): UploadState {
        return this.state;
    }

    public getVideoId(): string | null {
        return this.videoId;
    }

    private setState(nextState: UploadState): void {
        this.state = nextState;
        this.options.onStateChange?.(nextState);
    }

    public async start(): Promise<{ videoId: string; video?: Video; message?: string }> {
        try {
            if (this.isAborted) {
                throw new Error('Upload was aborted.');
            }

            // Step 1: Create draft video if not already provided
            if (!this.videoId) {
                this.setState('creating_draft');
                const defaultTitle = this.file.name.replace(/\.[^/.]+$/, '').trim() || 'Untitled Video';
                const createRes = await axios.post(
                    storeVideoRoute().url,
                    {
                        title: this.options.title || defaultTitle,
                        description: this.options.description || null,
                        visibility: this.options.visibility || 'private',
                    },
                    {
                        headers: {
                            Accept: 'application/json, text/html',
                            'X-Inertia': 'true',
                        },
                    },
                );

                const resolvedId =
                    createRes.data?.props?.video?.id ||
                    createRes.data?.video?.id ||
                    createRes.data?.id ||
                    this.extractVideoIdFromUrl(createRes.request?.responseURL || '') ||
                    this.extractVideoIdFromUrl(createRes.headers?.location || '');

                if (!resolvedId) {
                    throw new Error('Failed to create draft video record.');
                }
                this.videoId = String(resolvedId);
            }

            if (this.isAborted) {
                throw new Error('Upload was aborted.');
            }

            // Step 2: Initiate multipart upload session
            this.setState('initiating');
            const initiateRes = await axios.post(uploadInitiateRoute(this.videoId).url, {
                filename: this.file.name,
                file_size: this.file.size,
                content_type: resolveContentType(this.file),
            });

            this.uploadId = initiateRes.data.upload_id;
            this.partSize = Number(initiateRes.data.part_size) || 5 * 1024 * 1024;
            this.totalParts = Number(initiateRes.data.total_parts) || 1;

            if (this.isAborted) {
                throw new Error('Upload was aborted.');
            }

            // Step 3: Upload all chunks via worker pool
            this.setState('uploading');
            this.uploadStartTime = performance.now();
            this.partLoadedBytes.clear();
            this.completedParts = [];

            // Emit initial progress event
            this.emitProgress(1);

            await this.uploadAllParts();

            if (this.isAborted) {
                throw new Error('Upload was aborted.');
            }

            // Step 4: Complete multipart upload
            this.setState('completing');
            this.completedParts.sort((a, b) => a.PartNumber - b.PartNumber);

            const completeRes = await axios.post(uploadCompleteRoute(this.videoId).url, {
                parts: this.completedParts,
            });

            this.setState('completed');
            const result = {
                videoId: this.videoId,
                video: completeRes.data?.video || completeRes.data,
                message: completeRes.data?.message,
            };

            this.options.onSuccess?.(result);
            return result;
        } catch (error: unknown) {
            this.isAborted = true;
            for (const xhr of this.activeXhrs.values()) {
                try {
                    xhr.abort();
                } catch {
                    // Suppress abort errors
                }
            }
            this.activeXhrs.clear();

            if (this.state !== 'aborted') {
                this.setState('failed');
                // Server-side abort attempt on unrecoverable failure
                if (this.videoId && this.uploadId) {
                    try {
                        await axios.post(uploadAbortRoute(this.videoId).url, {});
                    } catch {
                        // Suppress abort errors on failure
                    }
                }
                const err =
                    error instanceof Error
                        ? error
                        : new Error(typeof error === 'object' && error && 'message' in error ? String(error.message) : 'Upload failed');
                this.options.onError?.(err);
                throw err;
            }
            throw new Error('Upload was aborted.');
        }
    }

    public async abort(): Promise<void> {
        if (this.isAborted || this.state === 'completed') {
            return;
        }

        this.isAborted = true;
        this.setState('aborted');

        // Cancel all active in-flight XHRs
        for (const xhr of this.activeXhrs.values()) {
            try {
                xhr.abort();
            } catch {
                // Ignore abort errors
            }
        }
        this.activeXhrs.clear();

        // Call server-side abort endpoint
        if (this.videoId && this.uploadId) {
            try {
                await axios.post(uploadAbortRoute(this.videoId).url, {});
            } catch (e) {
                console.warn('Failed to abort upload session on server:', e);
            }
        }
    }

    private async uploadAllParts(): Promise<void> {
        const queue: number[] = Array.from({ length: this.totalParts }, (_, i) => i + 1);
        const concurrency = Math.min(this.options.concurrency ?? 3, this.totalParts);

        const runWorker = async (): Promise<void> => {
            while (queue.length > 0 && !this.isAborted) {
                const partNumber = queue.shift();
                if (partNumber === undefined) break;

                const url = await this.getPartUrl(partNumber);
                if (this.isAborted) break;

                const start = (partNumber - 1) * this.partSize;
                const end = Math.min(start + this.partSize, this.file.size);
                const chunkBlob = this.file.slice(start, end);

                let retries = 0;
                const maxRetries = 2;
                let success = false;
                let lastError: Error | null = null;

                while (retries <= maxRetries && !success && !this.isAborted) {
                    try {
                        this.partLoadedBytes.set(partNumber, 0);
                        const result = await this.uploadChunk(partNumber, url, chunkBlob);
                        this.completedParts.push(result);
                        this.partLoadedBytes.set(partNumber, chunkBlob.size);
                        this.emitProgress(partNumber);
                        success = true;
                    } catch (err: unknown) {
                        lastError = err instanceof Error ? err : new Error(String(err));
                        retries++;
                        if (retries <= maxRetries && !this.isAborted) {
                            await new Promise((resolve) => setTimeout(resolve, 1000 * retries));
                        }
                    }
                }

                if (!success && !this.isAborted) {
                    throw lastError || new Error(`Failed to upload part ${partNumber}`);
                }
            }
        };

        const workers = Array.from({ length: concurrency }, () => runWorker());
        await Promise.all(workers);

        if (!this.isAborted && this.completedParts.length !== this.totalParts) {
            throw new Error(`Incomplete upload: expected ${this.totalParts} parts, completed ${this.completedParts.length}.`);
        }
    }

    private async getPartUrl(partNumber: number): Promise<string> {
        if (this.urlCache.has(partNumber)) {
            return this.urlCache.get(partNumber)!;
        }

        if (!this.videoId) {
            throw new Error('Video ID is required to fetch part URLs.');
        }

        // Batch upcoming parts: fetch up to 5 parts at a time
        const needed: number[] = [];
        for (let p = partNumber; p <= this.totalParts && needed.length < 5; p++) {
            if (!this.urlCache.has(p)) {
                needed.push(p);
            }
        }

        const res = await axios.post(uploadPartsRoute(this.videoId).url, {
            part_numbers: needed,
        });

        const data = res.data;
        const urlsMap = data.parts || data.urls || data;

        for (const p of needed) {
            const url = urlsMap[p] || urlsMap[String(p)];
            if (url) {
                this.urlCache.set(p, String(url));
            }
        }

        const targetUrl = this.urlCache.get(partNumber);
        if (!targetUrl) {
            throw new Error(`Could not obtain presigned URL for part ${partNumber}.`);
        }

        return targetUrl;
    }

    private uploadChunk(partNumber: number, url: string, blob: Blob): Promise<PartUploadResult> {
        return new Promise((resolve, reject) => {
            if (this.isAborted) {
                reject(new Error('Upload aborted'));
                return;
            }

            const xhr = new XMLHttpRequest();
            this.activeXhrs.set(partNumber, xhr);

            xhr.upload.addEventListener('progress', (e) => {
                if (this.isAborted) return;
                if (e.lengthComputable) {
                    this.partLoadedBytes.set(partNumber, e.loaded);
                    this.emitProgress(partNumber);
                }
            });

            xhr.addEventListener('load', () => {
                this.activeXhrs.delete(partNumber);
                if (this.isAborted) {
                    reject(new Error('Upload aborted'));
                    return;
                }

                if (xhr.status >= 200 && xhr.status < 300) {
                    const rawEtag = xhr.getResponseHeader('ETag') || xhr.getResponseHeader('etag') || '';
                    const etag = rawEtag.replace(/^"|"$/g, '').trim();
                    if (!etag) {
                        reject(
                            new Error(
                                `Missing ETag header on part ${partNumber} response. Ensure MinIO exposes the ETag header via Access-Control-Expose-Headers.`,
                            ),
                        );
                        return;
                    }
                    resolve({ PartNumber: partNumber, ETag: etag });
                } else {
                    reject(new Error(`Failed to upload part ${partNumber}: HTTP ${xhr.status} ${xhr.statusText}`));
                }
            });

            xhr.addEventListener('error', () => {
                this.activeXhrs.delete(partNumber);
                reject(new Error(`Network error while uploading part ${partNumber}`));
            });

            xhr.addEventListener('abort', () => {
                this.activeXhrs.delete(partNumber);
                reject(new Error(`Upload of part ${partNumber} was aborted`));
            });

            xhr.open('PUT', url);
            xhr.send(blob);
        });
    }

    private emitProgress(currentPart: number): void {
        const uploadedBytes = Array.from(this.partLoadedBytes.values()).reduce((sum, b) => sum + b, 0);
        const totalBytes = this.file.size;
        const percentage = totalBytes > 0 ? Math.min(100, Math.round((uploadedBytes / totalBytes) * 100)) : 0;

        const now = performance.now();
        const elapsedSeconds = (now - this.uploadStartTime) / 1000;
        const speedBytesPerSecond = elapsedSeconds > 0.1 ? uploadedBytes / elapsedSeconds : 0;

        const speedFormatted = formatSpeed(speedBytesPerSecond);
        const remainingBytes = Math.max(0, totalBytes - uploadedBytes);
        const etaSeconds = speedBytesPerSecond > 1024 ? Math.round(remainingBytes / speedBytesPerSecond) : null;

        const progress: UploadProgress = {
            uploadedBytes,
            totalBytes,
            percentage,
            speedBytesPerSecond,
            speedFormatted,
            estimatedTimeRemainingSeconds: etaSeconds,
            currentPart,
            totalParts: this.totalParts,
        };

        this.options.onProgress?.(progress);
    }

    private extractVideoIdFromUrl(url: string): string | null {
        if (!url) return null;
        const match = url.match(/\/panel\/videos\/([0-9a-fA-F-]+)/);
        return match ? match[1] : null;
    }
}
