import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatFileSize, validateFileSize, validateFileType } from '@/lib/file-validation';
import type { SystemSetting } from '@/types/system-setting';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { ExternalLink, ImageIcon, X } from 'lucide-react';
import { useRef, useState } from 'react';

interface ImageUploadProps {
    setting: SystemSetting;
    onChange: (file: File | null) => void;
}

export function ImageUpload({ setting, onChange }: ImageUploadProps) {
    const { __ } = useLocalizer();
    const [error, setError] = useState<string | null>(null);
    const [preview, setPreview] = useState<string | null>(setting.url || null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    // Get validation rules from setting
    const rules = typeof setting.rules === 'object' && setting.rules !== null ? setting.rules : {};
    const accept = (rules.accept as string | undefined) || 'image/*';
    const maxSize = rules.maxsize as number | undefined; // Size in KB, undefined means unlimited

    const handleFileSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        setError(null);

        // Validate file size only if maxSize is specified (maxSize is in KB)
        if (!validateFileSize(file, maxSize)) {
            setError(`File size must be less than ${formatFileSize(maxSize!)}`);
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
            return;
        }

        // Validate file type if accept is specified
        const isValidType = await validateFileType(file, accept);
        if (!isValidType) {
            setError(`Invalid file type. Accepted: ${accept}`);
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
            return;
        }

        // Set preview using FileReader
        const reader = new FileReader();
        reader.onloadend = () => {
            setPreview(reader.result as string);
        };
        reader.readAsDataURL(file);

        // Pass file to parent
        onChange(file);
    };

    const handleRemove = () => {
        onChange(null);
        setPreview(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    return (
        <div className="space-y-2">
            <Label htmlFor={setting.key}>{setting.name}</Label>
            <div className="flex gap-2">
                <Input id={setting.key} ref={fileInputRef} type="file" accept={accept} onChange={handleFileSelect} className="flex-1" />
            </div>
            {error && <p className="text-sm text-destructive">{error}</p>}
            <div className="w-32">
                {preview ? (
                    <div className="relative overflow-hidden rounded-md border">
                        <img
                            src={preview}
                            alt={setting.name}
                            className="h-32 w-full cursor-pointer object-cover"
                            onClick={() => window.open(preview, '_blank')}
                            title={__('Click to open in new tab')}
                        />
                        <div className="absolute top-2 right-2 flex gap-1">
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                onClick={() => window.open(preview, '_blank')}
                                title={__('Open image')}
                            >
                                <ExternalLink className="h-4 w-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                onClick={handleRemove}
                                title={__('Remove image')}
                                className="text-destructive hover:text-destructive"
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                ) : (
                    <div className="flex h-32 items-center justify-center rounded-md border border-dashed">
                        <ImageIcon className="h-8 w-8 text-muted-foreground" />
                    </div>
                )}
            </div>
            {maxSize && (
                <p className="text-xs text-muted-foreground">
                    {__('Max size:')} {formatFileSize(maxSize)}
                </p>
            )}
        </div>
    );
}
