import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { ChunkedUploader, formatBytes, type UploadProgress, type UploadState } from '@/lib/chunked-uploader';
import { cn } from '@/lib/utils';
import { show as showVideoRoute } from '@/routes/portal/videos';
import type { VideoVisibility } from '@/types/video';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { router } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Clock, Film, HardDrive, Loader2, RotateCcw, Upload, X, Zap } from 'lucide-react';
import React, { useRef, useState } from 'react';
import { toast } from 'sonner';

export interface UploadDialogProps {
    readonly open: boolean;
    readonly onOpenChange: (open: boolean) => void;
    readonly videoId?: string;
    readonly defaultVisibility?: VideoVisibility;
    readonly onSuccess?: (videoId: string) => void;
}

const SUPPORTED_EXTENSIONS = ['.mp4', '.mov', '.mkv', '.webm'];

function isValidVideo(file: File): boolean {
    const ext = '.' + file.name.split('.').pop()?.toLowerCase();
    if (SUPPORTED_EXTENSIONS.includes(ext)) {
        return true;
    }
    if (file.type && (file.type.startsWith('video/') || file.type === 'application/x-matroska')) {
        return true;
    }
    return false;
}

export default function UploadDialog({ open, onOpenChange, videoId, defaultVisibility = 'private', onSuccess }: UploadDialogProps) {
    const { __ } = useLocalizer();

    // File selection state
    const [file, setFile] = useState<File | null>(null);
    const [isDragOver, setIsDragOver] = useState(false);
    const [fileError, setFileError] = useState<string | null>(null);

    // Form metadata state
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [visibility, setVisibility] = useState<VideoVisibility>(defaultVisibility);
    const [titleError, setTitleError] = useState<string | null>(null);

    // Upload state
    const [uploadState, setUploadState] = useState<UploadState>('idle');
    const [progress, setProgress] = useState<UploadProgress | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    // Cancel confirmation state
    const [showCancelConfirm, setShowCancelConfirm] = useState(false);
    const [isCancelling, setIsCancelling] = useState(false);

    // Uploader instance reference
    const uploaderRef = useRef<ChunkedUploader | null>(null);
    const fileInputRef = useRef<HTMLInputElement | null>(null);

    const isUploading =
        uploadState === 'creating_draft' || uploadState === 'initiating' || uploadState === 'uploading' || uploadState === 'completing';

    const isCompleted = uploadState === 'completed';
    const isFailed = uploadState === 'failed';

    const resetForm = () => {
        setFile(null);
        setTitle('');
        setDescription('');
        setVisibility(defaultVisibility);
        setUploadState('idle');
        setProgress(null);
        setFileError(null);
        setTitleError(null);
        setErrorMessage(null);
        uploaderRef.current = null;
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const handleOpenChange = (nextOpen: boolean) => {
        if (!nextOpen && isUploading) {
            setShowCancelConfirm(true);
            return;
        }
        if (!nextOpen) {
            resetForm();
        }
        onOpenChange(nextOpen);
    };

    const handleSelectFile = (selectedFile: File) => {
        if (!isValidVideo(selectedFile)) {
            const err = __('Please select a supported video file (.mp4, .mov, .mkv, .webm).');
            setFileError(err);
            toast.error(err);
            return;
        }

        setFile(selectedFile);
        setFileError(null);

        // Prefill title with filename without extension if empty
        if (!title.trim()) {
            const rawName = selectedFile.name.replace(/\.[^/.]+$/, '').trim();
            setTitle(rawName);
            setTitleError(null);
        }
    };

    const handleDrop = (e: React.DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        setIsDragOver(false);

        if (isUploading) return;

        const droppedFiles = e.dataTransfer.files;
        if (droppedFiles.length > 0) {
            handleSelectFile(droppedFiles[0]);
        }
    };

    const handleDragOver = (e: React.DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        if (!isUploading) {
            setIsDragOver(true);
        }
    };

    const handleDragLeave = (e: React.DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        setIsDragOver(false);
    };

    const handleFileInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const files = e.target.files;
        if (files && files.length > 0) {
            handleSelectFile(files[0]);
        }
    };

    const handleStartUpload = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!file) {
            setFileError(__('Please select a video file to upload.'));
            return;
        }

        if (!title.trim()) {
            setTitleError(__('Title is required.'));
            return;
        }

        setTitleError(null);
        setFileError(null);
        setErrorMessage(null);

        const uploader = new ChunkedUploader({
            file,
            videoId,
            title: title.trim(),
            description: description.trim() || undefined,
            visibility,
            concurrency: 3,
            onStateChange: (state) => setUploadState(state),
            onProgress: (p) => setProgress(p),
            onSuccess: (result) => {
                setUploadState('completed');
                toast.success(__('Video uploaded successfully!'));
                onSuccess?.(result.videoId);
                setTimeout(() => {
                    handleOpenChange(false);
                    router.visit(showVideoRoute(result.videoId).url);
                }, 800);
            },
            onError: (err) => {
                setUploadState('failed');
                const msg = err.message || __('Upload failed. Please try again.');
                setErrorMessage(msg);
                toast.error(msg);
            },
        });

        uploaderRef.current = uploader;
        try {
            await uploader.start();
        } catch {
            // Handled in onError callback
        }
    };

    const handleConfirmCancel = async () => {
        setIsCancelling(true);
        try {
            if (uploaderRef.current) {
                await uploaderRef.current.abort();
            }
        } finally {
            setIsCancelling(false);
            setShowCancelConfirm(false);
            resetForm();
            toast.info(__('Upload cancelled.'));
            onOpenChange(false);
        }
    };

    const getStatusLabel = (): string => {
        switch (uploadState) {
            case 'creating_draft':
                return __('Creating video draft...');
            case 'initiating':
                return __('Initializing upload session...');
            case 'uploading':
                if (progress && progress.totalParts > 1) {
                    return __('Uploading chunk :current of :total...', {
                        current: progress.currentPart,
                        total: progress.totalParts,
                    });
                }
                return __('Uploading media...');
            case 'completing':
                return __('Finalizing upload...');
            case 'completed':
                return __('Upload complete!');
            case 'failed':
                return __('Upload failed');
            case 'aborted':
                return __('Upload cancelled');
            default:
                return __('Ready');
        }
    };

    return (
        <>
            <Dialog open={open} onOpenChange={handleOpenChange}>
                <DialogContent
                    className="sm:max-w-xl"
                    onPointerDownOutside={(e) => {
                        if (isUploading) e.preventDefault();
                    }}
                    onEscapeKeyDown={(e) => {
                        if (isUploading) {
                            e.preventDefault();
                            setShowCancelConfirm(true);
                        }
                    }}
                >
                    <form onSubmit={handleStartUpload}>
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2 text-xl font-semibold">
                                <Upload className="size-5 text-primary" />
                                {__('Upload Video')}
                            </DialogTitle>
                            <DialogDescription>{__('Directly upload large video files to cloud storage with resilient chunking.')}</DialogDescription>
                        </DialogHeader>

                        {/* Hidden file input */}
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".mp4,.mov,.mkv,.webm,video/mp4,video/quicktime,video/x-matroska,video/webm"
                            onChange={handleFileInputChange}
                            className="hidden"
                            disabled={isUploading}
                        />

                        <div className="mt-4 flex flex-col gap-4">
                            {/* Dropzone / File Picker */}
                            {!file ? (
                                <div
                                    onDrop={handleDrop}
                                    onDragOver={handleDragOver}
                                    onDragLeave={handleDragLeave}
                                    onClick={() => fileInputRef.current?.click()}
                                    className={cn(
                                        'flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 text-center transition-colors',
                                        isDragOver ? 'border-primary bg-primary/10' : 'border-border/70 hover:border-primary/50 hover:bg-muted/40',
                                        fileError && 'border-destructive bg-destructive/5',
                                    )}
                                >
                                    <div className="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                                        <Upload className="size-6" />
                                    </div>
                                    <h4 className="mt-3 text-sm font-semibold text-foreground">
                                        {__('Drag and drop your video file here, or click to browse')}
                                    </h4>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {__('Supported formats: MP4, MOV, MKV, WebM (up to 100 GB)')}
                                    </p>
                                    {fileError && <p className="mt-2 text-xs font-medium text-destructive">{fileError}</p>}
                                </div>
                            ) : (
                                /* Selected File Card */
                                <div className="flex items-center justify-between rounded-lg border border-border/70 bg-muted/30 p-3">
                                    <div className="flex min-w-0 items-center gap-3">
                                        <div className="flex size-10 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                                            <Film className="size-5" />
                                        </div>
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium text-foreground">{file.name}</p>
                                            <p className="text-xs text-muted-foreground">{formatBytes(file.size)}</p>
                                        </div>
                                    </div>

                                    {!isUploading && !isCompleted && (
                                        <div className="flex items-center gap-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => fileInputRef.current?.click()}
                                                className="h-8 text-xs text-muted-foreground hover:text-foreground"
                                            >
                                                {__('Change File')}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => {
                                                    setFile(null);
                                                    if (fileInputRef.current) fileInputRef.current.value = '';
                                                }}
                                                className="size-8 text-muted-foreground hover:text-destructive"
                                            >
                                                <X className="size-4" />
                                                <span className="sr-only">{__('Remove File')}</span>
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* Metadata Inputs (Title, Description, Visibility) */}
                            {file && !isCompleted && (
                                <div className="flex flex-col gap-3">
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="upload-video-title">
                                            {__('Title')} <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="upload-video-title"
                                            value={title}
                                            onChange={(e) => setTitle(e.target.value)}
                                            placeholder={__('e.g. Product Demo 2026')}
                                            disabled={isUploading}
                                            className={cn(titleError && 'border-destructive focus-visible:ring-destructive')}
                                        />
                                        {titleError && <p className="text-xs text-destructive">{titleError}</p>}
                                    </div>

                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="upload-video-description">{__('Description')}</Label>
                                        <Textarea
                                            id="upload-video-description"
                                            value={description}
                                            onChange={(e) => setDescription(e.target.value)}
                                            placeholder={__('Optional brief overview of the video...')}
                                            rows={2}
                                            disabled={isUploading}
                                        />
                                    </div>

                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="upload-video-visibility">{__('Visibility')}</Label>
                                        <Select
                                            value={visibility}
                                            onValueChange={(val) => setVisibility(val as VideoVisibility)}
                                            disabled={isUploading}
                                        >
                                            <SelectTrigger id="upload-video-visibility">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="private">
                                                    {__('Private')} —{' '}
                                                    <span className="text-xs text-muted-foreground">{__('Only organization members')}</span>
                                                </SelectItem>
                                                <SelectItem value="unlisted">
                                                    {__('Unlisted')} —{' '}
                                                    <span className="text-xs text-muted-foreground">{__('Anyone with direct link')}</span>
                                                </SelectItem>
                                                <SelectItem value="public">
                                                    {__('Public')} —{' '}
                                                    <span className="text-xs text-muted-foreground">{__('Publicly discoverable')}</span>
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            )}

                            {/* Live Upload Progress Section */}
                            {(isUploading || isCompleted) && (
                                <div className="flex flex-col gap-2 rounded-lg border border-border/70 bg-card p-4">
                                    <div className="flex items-center justify-between text-xs">
                                        <span className="flex items-center gap-1.5 font-medium text-foreground">
                                            {isUploading && <Loader2 className="size-3.5 animate-spin text-primary" />}
                                            {isCompleted && <CheckCircle2 className="size-3.5 text-emerald-500" />}
                                            {getStatusLabel()}
                                        </span>
                                        <span className="font-semibold text-foreground">{progress ? `${progress.percentage}%` : '0%'}</span>
                                    </div>

                                    <Progress value={progress?.percentage ?? 0} className="h-2.5" />

                                    <div className="flex flex-wrap items-center justify-between gap-2 pt-1 text-xs text-muted-foreground">
                                        <div className="flex items-center gap-1">
                                            <HardDrive className="size-3 text-muted-foreground" />
                                            <span>
                                                {formatBytes(progress?.uploadedBytes ?? 0)} / {formatBytes(file?.size ?? 0)}
                                            </span>
                                        </div>

                                        {isUploading && progress && (
                                            <div className="flex items-center gap-3">
                                                <div className="flex items-center gap-1">
                                                    <Zap className="size-3 text-cyan-500" />
                                                    <span>{progress.speedFormatted}</span>
                                                </div>

                                                {progress.estimatedTimeRemainingSeconds !== null && (
                                                    <div className="flex items-center gap-1">
                                                        <Clock className="size-3 text-muted-foreground" />
                                                        <span>
                                                            {__(':seconds s remaining', {
                                                                seconds: progress.estimatedTimeRemainingSeconds,
                                                            })}
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Failure Section */}
                            {isFailed && (
                                <div className="flex items-start gap-3 rounded-lg border border-rose-500/20 bg-rose-500/10 p-3 text-rose-600 dark:text-rose-400">
                                    <AlertCircle className="mt-0.5 size-4 shrink-0" />
                                    <div className="text-xs">
                                        <p className="font-medium">{__('Upload Failed')}</p>
                                        <p className="mt-0.5 text-muted-foreground">
                                            {errorMessage || __('An error occurred while uploading. Please try again.')}
                                        </p>
                                    </div>
                                </div>
                            )}

                            {/* Completed Banner */}
                            {isCompleted && (
                                <div className="flex items-center gap-3 rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-3 text-emerald-600 dark:text-emerald-400">
                                    <CheckCircle2 className="size-5 shrink-0" />
                                    <div className="text-xs">
                                        <p className="font-medium">{__('Upload complete!')}</p>
                                        <p className="text-muted-foreground">{__('Your video has been uploaded and queued for processing.')}</p>
                                    </div>
                                </div>
                            )}
                        </div>

                        <DialogFooter className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            {isUploading ? (
                                <Button type="button" variant="destructive" onClick={() => setShowCancelConfirm(true)} className="cursor-pointer">
                                    <X className="mr-1.5 size-4" />
                                    {__('Cancel Upload')}
                                </Button>
                            ) : isFailed ? (
                                <>
                                    <Button type="button" variant="outline" onClick={() => handleOpenChange(false)}>
                                        {__('Cancel')}
                                    </Button>
                                    <Button type="submit" className="cursor-pointer">
                                        <RotateCcw className="mr-1.5 size-4" />
                                        {__('Try Again')}
                                    </Button>
                                </>
                            ) : isCompleted ? (
                                <Button type="button" onClick={() => handleOpenChange(false)} className="cursor-pointer">
                                    {__('Done')}
                                </Button>
                            ) : (
                                <>
                                    <Button type="button" variant="outline" onClick={() => handleOpenChange(false)}>
                                        {__('Cancel')}
                                    </Button>
                                    <Button type="submit" disabled={!file} className="cursor-pointer">
                                        <Upload className="mr-1.5 size-4" />
                                        {__('Start Upload')}
                                    </Button>
                                </>
                            )}
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Cancel Confirmation Alert Dialog */}
            <AlertDialog open={showCancelConfirm} onOpenChange={setShowCancelConfirm}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{__('Cancel Upload?')}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {__('Are you sure you want to cancel this upload? The progress will be lost.')}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isCancelling}>{__('Continue Uploading')}</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleConfirmCancel}
                            disabled={isCancelling}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            {isCancelling ? (
                                <span className="flex items-center gap-1.5">
                                    <Loader2 className="size-4 animate-spin" />
                                    {__('Cancelling...')}
                                </span>
                            ) : (
                                __('Yes, Cancel Upload')
                            )}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
